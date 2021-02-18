<?php


namespace Test;


use PHPUnit\Framework\TestCase;
use Rudl\CertIssuer\CertManager;
use Rudl\LibGitDb\Type\Cert\T_Cert;
use Rudl\LibGitDb\Type\Cert\T_CertReqObj;
use Rudl\LibGitDb\Type\Cert\T_CertStateObj;

class CertManagerTest extends TestCase
{


    public function testCertManagerRequest()
    {

        $certRequestObj = new T_CertReqObj();
        $cert = new T_Cert();
        $cert->common_names = ["localhost", "localhost2"];
        $certRequestObj->certs[] = $cert;

        $stateObj = new T_CertStateObj();

        $certManager = new CertManager($certRequestObj, $stateObj, "/opt/www");


        $this->assertEquals(["localhost"], $certManager->getConnectedHosts($cert));

    }

}