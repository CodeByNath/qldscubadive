<?php

namespace QSD\Platform\Core;

class MailService
{
    public function register(): void
    {
        Health::register('mail', static fn() => defined('QSD_SMTP_HOST'));

        if (!defined('QSD_SMTP_HOST')) {
            return;
        }

        add_action('phpmailer_init', [$this, 'configure']);
    }

    /**
     * Configures PHPMailer to use an external SMTP transport.
     * Credentials are loaded from wp-config.php constants — never from this file.
     *
     * @param \PHPMailer\PHPMailer\PHPMailer $phpMailer
     */
    public function configure(object $phpMailer): void
    {
        $phpMailer->isSMTP();
        $phpMailer->Host     = QSD_SMTP_HOST;
        $phpMailer->SMTPAuth = true;
        $phpMailer->Port     = (int) QSD_SMTP_PORT;
        $phpMailer->Username = QSD_SMTP_USER;
        $phpMailer->Password = QSD_SMTP_PASS;

        // Port 465 = implicit SSL (SMTPS). Everything else = STARTTLS.
        $phpMailer->SMTPSecure = ((int) QSD_SMTP_PORT === 465) ? 'ssl' : 'tls';

        if (defined('QSD_SMTP_FROM') && QSD_SMTP_FROM !== '') {
            $phpMailer->From     = QSD_SMTP_FROM;
            $phpMailer->FromName = defined('QSD_SMTP_FROM_NAME') ? QSD_SMTP_FROM_NAME : 'Queensland Scuba Diving';
        }
    }
}
