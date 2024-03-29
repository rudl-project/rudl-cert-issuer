<?php


namespace Rudl\CertIssuer;


use Phore\FileSystem\PhoreTempFile;
use Rudl\LibGitDb\Type\Cert\T_Cert;
use Rudl\LibGitDb\Type\Cert\T_CertReqObj;
use Rudl\LibGitDb\Type\Cert\T_CertState;
use Rudl\LibGitDb\Type\Cert\T_CertStateObj;

class CertManager
{

    // Reissue Certificate 30 Days before timeout
    const REISSUE_BEFORE = 86400 * 30;

    // ReIssue Certificate 1 day after failure
    const ONFAIL_REISSUE = 86400;

    // ReIssue 1 day for changes
    const ISSUE_GRACE_PERIOD = 86400;

    const CHECK_HOST_PATH = "/.well-known/acme-challenge/";

    public function __construct (
        private T_CertReqObj $certRequests,
        private T_CertStateObj $certStateObj,
        private string $webRoot
    ){}


    /**
     * Return the connected hosts
     *
     * @param T_Cert $cert
     * @param array $disconnectedCommonNames
     * @return array
     * @throws \Phore\FileSystem\Exception\FileAccessException
     * @throws \Phore\FileSystem\Exception\FilesystemException
     * @throws \Phore\FileSystem\Exception\PathOutOfBoundsException
     */
    public function getConnectedHosts (T_Cert $cert, array &$disconnectedCommonNames = [])
    {
        $id = phore_random_str();
        $path = phore_dir($this->webRoot)->withSubPath(self::CHECK_HOST_PATH)->assertDirectory(true);
        $file = $path->withFileName($id)->set_contents($id);

        $validCns = [];

        foreach ($cert->common_names as $common_name) {
            try {
                $body = phore_http_request("http://{$common_name}" . self::CHECK_HOST_PATH . $id)->send()->getBody();
                if ($body !== $id) {
                    $disconnectedCommonNames[$common_name] = "Host challenge failed";
                    continue;
                }
            } catch (\Exception $e) {
                $disconnectedCommonNames[$common_name] = "Request error: " . $e->getMessage();
                continue;
            }
            $validCns[] = $common_name;
        }
        $file->unlink();
        return $validCns;

    }


    public function getCertReqToIssue(int $currentTs = null) : ?T_Cert
    {
        if ($currentTs === null)
            $currentTs = time();

        foreach ($this->certRequests->certs as $cert) {
            echo "\nScanning cert $cert->name";
            if ($cert->autoissue === false) {
                echo "[Skip - autoissue disabled]";
                continue;
            }

            $state = $this->certStateObj->getStateByName($cert->name);
            if ($state === null) {
                return $cert;
            }

            if (strtotime($state->last_issued_date) > $currentTs - self::ISSUE_GRACE_PERIOD) {
                echo "[Skip - not due 1]";
                continue;
            }

            if ($state->last_error_ts > $currentTs - self::ONFAIL_REISSUE) {
                echo "[Skip - not due 2]";
                continue;
            }

            $connectedCns = $this->getConnectedHosts($cert);
            $newCns = array_diff($connectedCns, $state->common_names);

            if (count ($newCns) > 0) {
                return $cert;
            }

            if ($state->cert_validTo < $currentTs + self::REISSUE_BEFORE) {
                return $cert;
            }
            echo "[Skip - not due 3]";
        }
        return null;
    }


    public function issueTestCert(T_Cert $cert, LetsEncryptRunner $letsEncryptRunner) : Cert
    {
        return $letsEncryptRunner->acquireTestCert($cert->common_names);
    }

    public function issueCert(T_Cert $cert, LetsEncryptRunner $letsEncryptRunner, &$errors = []) : Cert
    {
        $cns = $this->getConnectedHosts($cert);
        $errors = [];
        $certData = $letsEncryptRunner->acquireCert($cns, $errors);

        return $certData;


    }


}