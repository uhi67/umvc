<?php /** @noinspection PhpIllegalPsrClassPathInspection */

namespace uhi67\umvc;

/**
 * MailerInterface is implemented by mailer components, which can send an e-mail to the addressee.
 * You may configure a mailer component in your main config.
 *
 * @see MailerPhp
 * @see Fakemailer2
 */
interface MailerInterface
{
    /**
     * Sends mail to the address.
     * Must return true on success.
     *
     * Address can be either:
     * - [[address, name], ]
     * - [address, ...]
     * - or a single recipient string
     *
     * @param array[]|string[]|string $recipients -- e-mail address (mailto: prefix not allowed)
     * @param string $subject
     * @param array|string $message -- plain text or [HTML, plain]
     * @param array $options -- [from, replyto, headers]
     * @return bool
     */
    public function send(array|string $recipients, string $subject, array|string $message, array $options = []): bool;

    /**
     * Must return null on success or the last exception message on failure.
     *
     * @return string|null
     */
    public function getStatus(): ?string;
}
