<?php
namespace Dotsystems\App\Parts;

use Dotsystems\App\Parts\Databaser;

class Sms {
    /**
     * Validates a phone number using the specified provider.
     *
     * @param mixed $provider Object or class name of the provider
     * @param string $phone Phone number to validate
     * @param callable|null $callback Optional callback for custom validation
     * @param array $constructorArgs Optional constructor arguments for provider instantiation
     * @return bool Result of the provider's validatePhoneNumber method
     * @throws \InvalidArgumentException If provider is invalid
     * @throws \RuntimeException If validation fails
     */
    public static function validatePhoneNumber($provider, $phone, $callback = null, array $constructorArgs = []) {
        try {
            // Case 1: $provider is an object (already instantiated)
            if (is_object($provider)) {
                return $provider->validatePhoneNumber($phone, $callback);
            }

            // Case 2: $provider is a string (class name)
            if (is_string($provider) && class_exists($provider)) {
                // Create provider instance with or without constructor arguments
                $providerInstance = $constructorArgs 
                    ? new $provider(...$constructorArgs) // Spread arguments if provided
                    : new $provider(); // No arguments for simple providers
                return $providerInstance->validatePhoneNumber($phone, $callback);
            }

            // Invalid provider
            throw new \InvalidArgumentException('Provider must be an object or a valid class name.');
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to validate phone number: ' . $e->getMessage());
        }
    }

    /**
     * Sends an SMS using the specified provider.
     *
     * @param mixed $provider Object or class name of the provider
     * @param string $phone Phone number to send the SMS to
     * @param string $message Message content
     * @param array $constructorArgs Optional constructor arguments for provider instantiation
     * @return mixed Result of the provider's send method
     * @throws \InvalidArgumentException If provider is invalid
     * @throws \RuntimeException If SMS sending fails
     */
    public static function send($provider, $phone, $message, array $constructorArgs = []) {
        try {
            // Case 1: $provider is an object (already instantiated)
            if (is_object($provider)) {
                return $provider->send($phone, $message);
            }

            // Case 2: $provider is a string (class name)
            if (is_string($provider) && class_exists($provider)) {
                // Create provider instance with or without constructor arguments
                $providerInstance = $constructorArgs 
                    ? new $provider(...$constructorArgs) // Spread arguments if provided
                    : new $provider(); // No arguments for simple providers
                return $providerInstance->send($phone, $message);
            }

            // Invalid provider
            throw new \InvalidArgumentException('Provider must be an object or a valid class name.');
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to send SMS: ' . $e->getMessage());
        }
    }

    /**
     * Receives an SMS using the specified provider.
     *
     * @param mixed $provider Object or class name of the provider
     * @param string $phone Phone number to receive the SMS from
     * @param string $message Message content
     * @param array $constructorArgs Optional constructor arguments for provider instantiation
     * @return mixed Result of the provider's receive method
     * @throws \InvalidArgumentException If provider is invalid
     * @throws \RuntimeException If SMS receiving fails
     */
    public static function receive($provider, $phone, $message, array $constructorArgs = []) {
        try {
            // Case 1: $provider is an object (already instantiated)
            if (is_object($provider)) {
                return $provider->receive($phone, $message);
            }

            // Case 2: $provider is a string (class name)
            if (is_string($provider) && class_exists($provider)) {
                // Create provider instance with or without constructor arguments
                $providerInstance = $constructorArgs 
                    ? new $provider(...$constructorArgs) // Spread arguments if provided
                    : new $provider(); // No arguments for simple providers
                return $providerInstance->receive($phone, $message); // Opravené: volá receive namiesto send
            }

            // Invalid provider
            throw new \InvalidArgumentException('Provider must be an object or a valid class name.');
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to receive SMS: ' . $e->getMessage());
        }
    }

    /**
     * Retrieves the status of a sent SMS message using the specified provider.
     *
     * @param mixed $provider Object or class name of the provider
     * @param string $messageId The unique identifier of the SMS message
     * @param array $constructorArgs Optional constructor arguments for provider instantiation
     * @return mixed Result of the provider's getStatus method
     * @throws \InvalidArgumentException If provider is invalid
     * @throws \RuntimeException If retrieving the status fails
     */
    public static function getStatus($provider, $messageId, array $constructorArgs = []) {
        try {
            // Case 1: $provider is an object (already instantiated)
            if (is_object($provider)) {
                return $provider->getStatus($messageId);
            }

            // Case 2: $provider is a string (class name)
            if (is_string($provider) && class_exists($provider)) {
                // Create provider instance with or without constructor arguments
                $providerInstance = $constructorArgs 
                    ? new $provider(...$constructorArgs) // Spread arguments if provided
                    : new $provider(); // No arguments for simple providers
                return $providerInstance->getStatus($messageId);
            }

            // Invalid provider
            throw new \InvalidArgumentException('Provider must be an object or a valid class name.');
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to retrieve SMS status: ' . $e->getMessage());
        }
    }

    /**
     * Sets configuration options for the specified provider.
     *
     * @param mixed $provider Object or class name of the provider
     * @param mixed $config Configuration settings for the provider (e.g., API keys, endpoints)
     * @param array $constructorArgs Optional constructor arguments for provider instantiation
     * @return void
     * @throws \InvalidArgumentException If provider is invalid
     * @throws \RuntimeException If setting configuration fails
     */
    public static function setConfig($provider, $config, array $constructorArgs = []) {
        try {
            // Case 1: $provider is an object (already instantiated)
            if (is_object($provider)) {
                return $provider->setConfig($config);
            }

            // Case 2: $provider is a string (class name)
            if (is_string($provider) && class_exists($provider)) {
                // Create provider instance with or without constructor arguments
                $providerInstance = $constructorArgs 
                    ? new $provider(...$constructorArgs) // Spread arguments if provided
                    : new $provider(); // No arguments for simple providers
                return $providerInstance->setConfig($config);
            }

            // Invalid provider
            throw new \InvalidArgumentException('Provider must be an object or a valid class name.');
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to set provider configuration: ' . $e->getMessage());
        }
    }

}
?>
