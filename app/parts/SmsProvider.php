<?php

namespace Dotsystems\App\Parts;

/**
 * Interface SmsProvider
 *
 * Defines the contract for SMS provider implementations, enabling sending, receiving,
 * validating, and managing SMS messages through a standardized interface.
 */
interface SmsProvider {
    /**
     * Sends an SMS message to the specified phone number.
     *
     * @param string $phoneNumber The recipient's phone number.
     * @param string $message The content of the SMS message.
     * @param array $options Optional parameters for customizing the send operation.
     * @return mixed The result of the send operation, as defined by the provider.
     */
    public function send($phoneNumber, $message, $options = []);

    /**
     * Receives SMS messages based on the specified filter.
     *
     * @param mixed $filter Criteria to filter incoming SMS messages (e.g., sender, time range).
     * @return mixed The result of the receive operation, as defined by the provider.
     */
    public function receive($filter);

    /**
     * Validates a phone number to ensure it is in a correct format.
     *
     * @param string $phoneNumber The phone number to validate.
     * @return bool True if the phone number is valid, false otherwise.
     */
    public function validatePhoneNumber($phoneNumber): bool;

    /**
     * Retrieves the status of a sent SMS message.
     *
     * @param string $messageId The unique identifier of the SMS message.
     * @return mixed The status of the message, as defined by the provider.
     */
    public function getStatus($messageId);

    /**
     * Sets configuration options for the SMS provider.
     *
     * @param mixed $config Configuration settings for the provider (e.g., API keys, endpoints).
     * @return void
     */
    public function setConfig($config);
}

?>