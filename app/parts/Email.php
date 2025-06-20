<?php
namespace Dotsystems\App\Parts;

// Fasada pre emailer

class Email {

    /**
    * Sends an email using SMTP configuration.
    *
    * @param string $account The account configuration used for sending the email.
    * @param string|array $to The recipient email(s). Can be a single email address or an array of email addresses.
    * @param string $subject The email subject.
    * @param string $body The email body content.
    * @param string|null $contentType The content type of the email body. Default is 'text/html'.
    * @param array $attachments An array of file paths to be attached to the email.
    * @param array $cc An array of CC email addresses.
    * @param array $bcc An array of BCC email addresses.
    *
    * @return bool|array Returns true if the email is sent successfully. Otherwise, returns an array of error messages.
    *
    * @throws \Exception If the SMTP configuration for the specified account is not found in the config.php file.
    */
    public static function send($account, $to, $subject, $body, $contentType = null, $attachments=[], $cc=[], $bcc=[]) {
        $settings = Config::email($account,"smtp");

        if ($contentType === null) $contentType = 'text/html';
        
        if (is_string($to)) {
            $to = explode(',', $to);
        }
        
        if (is_string($cc)) {
            $cc = explode(',', $cc);
        }
        
        if (is_string($bcc)) {
            $bcc = explode(',', $bcc);
        }
        
        if (isset($settings)) {
            $smtpConfig = [
                'host' => $settings['host'],
                'port' => $settings['port'],
                'timeout' => $settings['timeout'],
                'secure' => $settings['secure'],
                'saveEmail' => false,
            ];
            $emailer = new Emailer($smtpConfig);
            $emailer->setCredentials($settings['username'], $settings['password'], null, null);
            $result = $emailer->sendEmail($settings['from'], $to, $cc, $bcc, $subject, $body, $contentType, $attachments);
            
            if ($result) {
                return true;
            } else {
                return $emailer->getErrors();
            }
        } else {
            throw new \Exception('Add smtp configuration for account: '.$account.' in config.php file!');
        }

    }
    
    /**
     * Sends an email and saves it to a specified folder using SMTP and IMAP configurations.
     *
     * @param string $folder The folder where the sent email will be saved.
     * @param string $account The account configuration used for sending and saving the email.
     * @param string|array $to The recipient email(s). Can be a single email address or an array of email addresses.
     * @param string $subject The email subject.
     * @param string $body The email body content.
     * @param string|null $contentType The content type of the email body. Default is 'text/html'.
     * @param array $attachments An array of file paths to be attached to the email.
     * @param array $cc An array of CC email addresses.
     * @param array $bcc An array of BCC email addresses.
     *
     * @return bool|array Returns true if the email is sent and saved successfully. Otherwise, returns an array of error messages.
     *
     * @throws \Exception If the SMTP or IMAP configuration for the specified account is not found in the config.php file.
     */
    public static function sendAndSave($folder, $account, $to, $subject, $body, $contentType = null, $attachments=[], $cc=[], $bcc=[]) {
        $settings = Config::email($account,"smtp");
        $settings2 = Config::email($account,"imap");

        if ($contentType === null) $contentType = 'text/html';
        
        if (is_string($to)) {
            $to = explode(',', $to);
        }
        
        if (is_string($cc)) {
            $cc = explode(',', $cc);
        }
        
        if (is_string($bcc)) {
            $bcc = explode(',', $bcc);
        }
        
        if (isset($settings) && isset($settings2)) {
            $smtpConfig = [
                'host' => $settings['host'],
                'port' => $settings['port'],
                'timeout' => $settings['timeout'],
                'secure' => $settings['secure'],
                'saveEmail' => true,
            ];
            $imapConfig = [
                'host' => $settings2['host'],
                'port' => $settings2['port'],
                'timeout' => $settings2['timeout'],
                'secure' => $settings2['secure'],
            ];
            $emailer = new Emailer($smtpConfig,$imapConfig);
            $emailer->setCredentials($settings['username'], $settings['password'], $settings2['username'], $settings2['password']);
            $result = $emailer->sendEmail($settings['from'], $to, $cc, $bcc, $subject, $body, $contentType, $attachments, $folder);
            
            if ($result) {
                return true;
            } else {
                return $emailer->getErrors();
            }
        } else {
            throw new \Exception('Add smtp and imap configuration for account: '.$account.' in config.php file!');
        }
    }
}

?>