<?php
/** @noinspection PhpIllegalPsrClassPathInspection */

/**
 * class FakeMailer
 *
 * @author Peter Uherkovich (uherkovich.peter@gmail.com)
 * @copyright (c) 2019.
 * @license MIT
 */

namespace uhi67\umvc;

/**
 * Fake mailer which saves sent mails into files
 *
 * ##Example for main config
 * ```
 *      'mailer' => [
 *          'class' => 'FakeMailer',
 *          'dir' => $datapath . '/FakeMail',
 *      ],
 * ```
 * @property-read $status
 */
class FakeMailer extends Component implements MailerInterface
{
    public string $dir;
    /**
     * @var mixed
     */
    private string|null $_status = null;

    /**
     * @param array|string[] $recipients --
     * @param string $subject
     * @param array|string $message -- plain text or [HTML, plain]
     * @param array $options -- [from, replyto, headers]
     * @return bool
     */
    public function send(array|string $recipients, string $subject, array|string $message, array $options = []): bool
    {
        $this->_status = null;
        if (!is_dir($this->dir) && is_dir(dirname($this->dir))) {
            @mkdir($this->dir);
        }
        if (!is_dir($this->dir)) {
            $this->_status = 'Output directory does not exist';
            return false;
        }

        $filename = $this->dir . '/' . date('ymd_His') . '_' . substr(md5(json_encode($recipients)), 0, 8) . '.eml';
        $f = fopen($filename, 'w');
        if (!$f) {
            $this->_status = 'Output directory is not writable';
            return false;
        }
        foreach ($options as $key => $value) {
            fputs($f, "$key: $value\n");
        }
        if (is_array($message)) {
            file_put_contents($filename.'.html', $message[0]);
            $message = $message[1];
        }
        fputs($f, "To: " . json_encode($recipients) . "\n");
        fputs($f, "Subject: $subject\n\n$message");
        fclose($f);
        return true;
    }

    public function getStatus(): ?string
    {
        return $this->_status;
    }
}
