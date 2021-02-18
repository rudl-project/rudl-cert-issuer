<?php

namespace App;

use Rudl\CertIssuer\CertManager;
use Rudl\LibGitDb\RudlGitDbClient;
use Rudl\LibGitDb\Type\Cert\T_CertReqObj;

require __DIR__ . "/../vendor/autoload.php";



$gitDb = new RudlGitDbClient(GITDB_HOST);

$list = $gitDb->listObjects(CERT_SCOPE);

$issuerObj = phore_hydrate(
    $list->getObject("certs.yml"),
    T_CertReqObj::class
);
assert($issuerObj instanceof T_CertReqObj);

$stateObj = $list->getObject("certs_state.json");


$certManager = new CertManager($issuerObj, $stateObj, "/opt/www");

while(($certRequest = $certManager->getCertReqToIssue()) !== null) {



}
