<?php
namespace Dotsystems\App\Parts;

trait StaticGetSet {
    /**
     * Retrieves the value of a static property in the current class.
     *
     * This method allows retrieving the value of a static property in the current class.
     * It checks if the provided property name is a non-empty string and if the property exists in the class.
     * If the conditions are met, it returns the property value. Otherwise, it returns null.
     *
     * @param string $name The name of the static property to retrieve.
     *
     * @return mixed|null Returns the value of the static property if it exists, null otherwise.
     */
    public static function getStatic(string $name) {
        if (!is_string($name) || empty($name)) {
            return null;
        }
        try {
            $reflection = new \ReflectionClass(static::class);
            if ($reflection->hasProperty($name) && $reflection->getProperty($name)->isStatic()) {
                $property = $reflection->getProperty($name);
                $property->setAccessible(true); // Allow access to private/protected properties
                return $property->getValue();
            }
        } catch (\ReflectionException $e) {
            // Handle cases where the class or property cannot be accessed
        }
        return null;
    }

    /* Sets the value of a static property by name in the current class.
    *
    * @param string $name The name of the static property to set.
    * @param mixed $value The value to assign to the static property.
    * @return bool True if the property was set successfully, false if the property doesn't exist or the input is invalid.
    */
    public static function setStatic(string $name, $value) {
        if (!is_string($name) || empty($name)) {
            return false;
        }
        try {
            $reflection = new \ReflectionClass(static::class);
            if ($reflection->hasProperty($name) && $reflection->getProperty($name)->isStatic()) {
                $property = $reflection->getProperty($name);
                $property->setAccessible(true); // Allow access to private/protected properties
                $property->setValue($value);
                return true;
            }
        } catch (\ReflectionException $e) {
            // Handle cases where the class or property cannot be accessed
        }
        return false;
    }
}

?>