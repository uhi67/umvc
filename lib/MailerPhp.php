<?php /** @noinspection PhpIllegalPsrClassPathInspection */

namespace educalliance\umvc;

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

/**
 * A MailerInterface wrapper for the well-known PHPMailer class
 *
 */
class MailerPhp extends Component implements MailerInterface
{
    /** @var string $host -- Set the SMTP server to send through */
    public string $host;
    /** @var int|null SMTP port, defaults to 25 */
    public ?int $port = null;
    /** @var string|null $username SMTP username */
    public ?string $username = null;
    /** @var string|null $password SMTP password */
    public ?string $password = null;
    /** @var array|string $from -- sender address or [address, name] */
    public array|string $from;
    public int $debug = SMTP::DEBUG_OFF; // SMTP::DEBUG_SERVER
    private ?string $_status;

    /**
     * @throws Exception -- if PHPMailer is not installed
     */
    public function init(): void {
        if(!class_exists(PHPMailer::class)) {
            throw new Exception('MailerPhp: The required PHPMailer is not installed. Run `composer require phpmailer/phpmailer` to install it.');
        }
    }

    /**
     * @param string[]|string $recipients -- [[address, name], ...] -- or a single recipient
     * @param string $subject
     * @param array|string $message -- html/plain or plain
     * @param array $options -- [from, replyto, timeout]
     * @return bool
     */
    public function send($recipients, string $subject, array|string $message, array $options = []): bool
    {
        $mail = new PHPMailer(true);

        try {
            $this->_status = null;
            //Server settings
            $mail->SMTPDebug = $this->debug;
            $mail->isSMTP();
            $mail->Host = $this->host;
            $mail->SMTPAuth = !!$this->username;
            if ($this->username) {
                $mail->Username = $this->username;
                $mail->Password = $this->password;
            }
            //$mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;      //Enable implicit TLS encryption
            $mail->Port = $this->port ?: 25;                        //TCP port to connect to; 465 use 587 if you have set `SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS`

            //Recipients
            $from = $options['from'] ?? $this->from;
            if (!$from) {
                throw new Exception('MailerPhp: missing from option');
            }
            $mail->setFrom(is_array($from) ? $from[0] : $from, is_array($from) ? $from[1] : '');
            if (!is_array($recipients)) {
                $recipients = [$recipients];
            }
            foreach ($recipients as $item) {
                if (!is_array($item)) {
                    $item = [$item, ''];
                }
                $mail->addAddress($item[0], $item[1]);
            }
            /** @var string|string[] $replyTo */
            $replyTo = $options['replyto'] ?? [];
            $replyTo = (array)$replyTo;
            foreach ($replyTo as $item) {
                if (!is_array($item)) {
                    $item = [$item, ''];
                }
                $mail->addReplyTo($item[0], $item[1]);
            }

            //Content
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->isHTML(is_array($message));                                  //Set email format to HTML
            $mail->Subject = $subject;
            $mail->Body = is_array($message) ? $message[0] : $message;
            $mail->AltBody = is_array($message) ? $message[1] : $message;
            $mail->Timeout = (int)($options['timeout'] ?? 8);

            $mail->send();
        } catch (Exception $e) {
            App::log('error', $msg = 'PhpMailer error: ' . $this->_status = $e->getMessage());
            if (php_sapi_name() == "cli") {
                echo $msg, "\n";
            }
            return false;
        }
        return true;
    }

    /**
     * Returns null on success or the last exception message on failure.
     *
     * @return string|null
     */
    public function getStatus(): ?string
    {
        return $this->_status;
    }
}
