<?php
/**
 * php artisan alert-ssl-certificate:check
 */

namespace App\Console\Commands\Alerts;

use App\Helpers\ArrayHelper;
use App\Helpers\Development;
use App\Helpers\TelegramHelper;
use Illuminate\Console\Command;

class AlertSslCertificate extends Command
{
    protected $signature = 'alert-ssl-certificate:check {--days=5 : Minimum number of days before the certificate expires}';
    protected $description = 'Check SSL certificate expiration';

    private string $certificatePath = '';

    public function handle()
    {
        $this->certificatePath = (string) (config('services.ssl.certificate_path')
            ?: '/etc/letsencrypt/live/' . parse_url((string) config('app.url'), PHP_URL_HOST) . '/fullchain.pem');

        $timeStart = microtime(true);

        $this->process();

        $this->info('Memory used: ' . Development::getPeakMemoryUsageInMb());
        $this->info('Execution time (s): ' . Development::getFormatDiffMicroTime($timeStart));
    }

    private function process()
    {
        $minimumDays = (int) $this->option('days');

        if (!file_exists($this->certificatePath)) {
            $message = [
                '❌ SSL certificate error' => "\n",
                'Certificate file not found: ' => $this->certificatePath,
            ];

            $this->error('Certificate file not found: ' . $this->certificatePath);
            return;
        }

        $certificateInfo = $this->getCertificateInfo();

        if (!$certificateInfo) {
            $message = [
                '❌ SSL certificate error' => "\n",
                'Failed to read certificate information' => '',
            ];

            $this->error('Failed to read certificate information');
            return;
        }

        $expirationDate = $certificateInfo['validTo_time_t'];
        $currentTime = time();
        $daysRemaining = floor(($expirationDate - $currentTime) / 86400);

        if ($daysRemaining < 0) {
            $message = [
                '🔴 SSL certificate has expired!' => "\n",
                'Domain: ' => $certificateInfo['subject']['CN'] ?? 'Unknown',
                'Expiration date: ' => date('Y-m-d H:i:s', $expirationDate),
                'Expired for: ' => abs($daysRemaining) . ' days',
            ];

            $this->error('SSL certificate expired ' . abs($daysRemaining) . ' days ago!');
        } elseif ($daysRemaining < $minimumDays) {
            $message = [
                '⚠️ SSL certificate expires soon!' => "\n",
                'Domain: ' => $certificateInfo['subject']['CN'] ?? 'Unknown',
                'Expiration date: ' => date('Y-m-d H:i:s', $expirationDate),
                'Days left: ' => $daysRemaining,
                'The certificate must be renewed!' => '',
            ];

            $this->warn('SSL certificate expires in ' . $daysRemaining . ' days!');
        } else {
            $this->info('SSL certificate is valid. Days left: ' . $daysRemaining);
            $this->info('Domain: ' . ($certificateInfo['subject']['CN'] ?? 'Unknown'));
            $this->info('Expiration date: ' . date('Y-m-d H:i:s', $expirationDate));
        }
    }

    private function getCertificateInfo(): array|false
    {
        $certificateContent = file_get_contents($this->certificatePath);

        if ($certificateContent === false) {
            return false;
        }

        $certificate = openssl_x509_read($certificateContent);

        if ($certificate === false) {
            return false;
        }

        $certificateData = openssl_x509_parse($certificate);
        openssl_x509_free($certificate);

        return $certificateData ?: false;
    }
}
