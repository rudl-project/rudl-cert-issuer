<?php


namespace Rudl\CertIssuer;


use Phore\Letsencrypt\PhoreCert;
use Phore\System\PhoreProc;

class LetsEncryptRunner
{

    public function __construct(
        private string $webroot,
        private string $tosEMail
    ){}


    public function acquireTestCert(array $domains) : Cert
    {
        $cert = new Cert();
        $cert->cds = $domains;
        $cert->issued_at = time();
        $cert->cert = "certdata";
        $cert->chain = "certdata";
        $cert->fullchain = "certdata";
        $cert->privkey = "certdata";
        return $cert;

    }


    public function acquireCert (array $domains, array &$errors=[]) : Cert
    {
        $tmppath = phore_dir("/tmp/certbot_" . uniqid());
        $tmppath->mkdir(0700);
        $errors = [];

        $domainParams = [];
        $firstDomain = null;
        $connectedDomains = $domains;

        foreach ($connectedDomains as $domain) {
            if ($firstDomain === null)
                $firstDomain = $domain;
            $domainParams[] = "-d " . escapeshellarg($domain);
        }
        if ($firstDomain === null) {
            $errors[] = ["domain" => null, "error" => "no connected domain (Requesting certs for: " . implode(", ", $domains) . ")"];
            throw new \InvalidArgumentException("No domain is mapped to this service: " . implode(", ", $domains));
        }

        $domainParams = implode(" ", $domainParams);

        try {


            $proc = new PhoreProc(
                "certbot certonly -n --agree-tos -m :email --logs-dir :path --config-dir :path --work-dir :path --webroot -w :webroot $domainParams",
                [
                    "email" => $this->tosEMail,
                    "path" => $tmppath->getUri(),
                    "webroot" => $this->webroot
                ]
            );
            $proc->setTimeout(120);
            $proc->wait();

            $crtPath = $tmppath->withSubPath("live")->withSubPath($firstDomain);
            $crtPath->assertDirectory();

            $cert = new Cert();
            $cert->cds = $connectedDomains;
            $cert->issued_at = time();
            $cert->cert = $crtPath->withFileName("cert.pem")->get_contents();
            $cert->chain = $crtPath->withFileName("chain.pem")->get_contents();
            $cert->fullchain = $crtPath->withFileName("fullchain.pem")->get_contents();
            $cert->privkey = $crtPath->withFileName("privkey.pem")->get_contents();
            $cert->parse();

            phore_exec("rm -Rf :path", ["path" => $tmppath->getUri()]);
            return $cert;

        } catch (\Exception $e) {
            phore_exec("rm -Rf :path", ["path" => $tmppath->getUri()]);
            throw $e;
        }
    }

    public function getChallengeByKey(string $key) : string
    {
        return phore_dir($this->webroot)->withSubPath(".well-known/acme-challenge")->withFileName($key)->get_contents();
    }

}