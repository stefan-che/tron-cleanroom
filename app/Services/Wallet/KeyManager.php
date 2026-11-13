<?php

namespace App\Services\Wallet;

use App\Models\Setting;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class KeyManager
{
    private string $binaryPath;
    private string $binaryFile = 'encryptor';

    public function __construct()
    {
       $this->binaryPath = base_path('enc/');
    }

    public function executeCommand(string $action, array $params): string
    {
        $args = [$action];

        foreach ($params as $param) {
            $args[] = $param;
        }

        $process = new Process(array_merge([$this->binaryPath . $this->binaryFile], $args));
        $process->setWorkingDirectory($this->binaryPath);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $process->getOutput();
    }

    /**
     * @param string $stringToEncrypt
     * @param int $connect
     * @return array
     * @throws \JsonException
     */
    public function encrypt(string $stringToEncrypt, int $connect): array
    {
        $result = $this->executeCommand('encrypt', [
            $connect,
            $stringToEncrypt,
        ]);

        return json_decode($result, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param string $encryptedPart
     * @param string $secret
     * @param string $salt
     * @param int $connect
     * @return array
     * @throws \JsonException
     */
    public function decrypt(string $encryptedPart, string $secret, string $salt, int $connect): array
    {
        $result = $this->executeCommand('decrypt', [
            $connect,
            $encryptedPart,
            $secret,
            $salt,
        ]);

        return json_decode($result, true, 512, JSON_THROW_ON_ERROR);
    }

    public function encryptBd(string $stringToEncrypt): string
    {
        return $this->executeCommand('encrypt_bd', [$stringToEncrypt]);
    }

}
