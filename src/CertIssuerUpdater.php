<?php


namespace Rudl\CertIssuer;


use Rudl\LibGitDb\RudlGitDbClient;
use Rudl\LibGitDb\Type\Cert\T_CertReqObj;
use Rudl\LibGitDb\Type\Cert\T_CertState;
use Rudl\LibGitDb\Type\Cert\T_CertStateObj;
use Rudl\LibGitDb\Type\Transport\T_Object;
use Rudl\LibGitDb\Type\Transport\T_ObjectList;

class CertIssuerUpdater
{

    public function __construct(
        private RudlGitDbClient $gitDb
    ){}


    private function getOrCreateStateObject(T_ObjectList $objectList, string $stateObjectName) : T_CertStateObj
    {
        $stateObj = $objectList->getObject(CERT_STATE_OBJECT)?->hydrate(T_CertStateObj::class);
        if ($stateObj === null) {
            $objectList = new T_ObjectList([
                (new T_Object(CERT_STATE_OBJECT))->dehydrate($stateObj = new T_CertStateObj())
            ]);
            $this->gitDb->writeObjects(SSL_CERT_SCOPE, $objectList);
        }
        return $stateObj;
    }


    public function __invoke()
    {
        $objectList = $this->gitDb->listObjects(SSL_CERT_SCOPE);
        $stateObj = $this->getOrCreateStateObject($objectList, CERT_STATE_OBJECT);
        $certReqObj = $objectList->getObject(CERT_REQ_OBJECT)?->hydrate(T_CertReqObj::class);
        if ($certReqObj === null)
            throw new \InvalidArgumentException("Cert Req object '" . CERT_REQ_OBJECT . "' not existing in scope '" . SSL_CERT_SCOPE . "'");

        $manager = new CertManager($certReqObj, $stateObj, "/opt/www");
        while (($cert = $manager->getCertReqToIssue()) !== null) {
            echo "Issuing cert '$cert->name'...\n";
            $state = $stateObj->getStateByName($cert->name);
            if ($state === null) {
                $stateObj->state[] = $state = new T_CertState($cert->name);
            }

            $state->last_issued_date = time();
            $this->gitDb->writeObjects(SSL_CERT_SCOPE, new T_ObjectList([
                (new T_Object(CERT_STATE_OBJECT))->dehydrate($stateObj)
            ]));

            if (DEV_MODE) {
                $certData = $manager->issueTestCert($cert, new LetsEncryptRunner("/opt/www", TOS_EMAIL));
            } else {
                $certData = $manager->issueCert($cert, new LetsEncryptRunner("/opt/www", TOS_EMAIL), $errors);
            }

            $state->common_names = $certData->cds;
            $state->last_error_ts = 0;
            $state->cert_validTo = $certData->cert_validTo;
            $state->valid_to_date = date("Y-m-d H:i:s", $certData->cert_validTo);
            $state->cert_validFrom = $certData->cert_validFrom;
            $state->cert_serial = $certData->cert_serialNumber;
            $state->last_issued_date = date("Y-m-d H:i:s", $certData->issued_at);

            $this->gitDb->writeObjects(SSL_CERT_SCOPE, new T_ObjectList([
                (new T_Object(CERT_STATE_OBJECT))->dehydrate($stateObj),
                new T_Object($cert->name, $certData->getPemFullcain(), true)
            ]));
            echo "Successfully issued.\n";
        }


    }
}