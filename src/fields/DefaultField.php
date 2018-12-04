<?php
namespace verbb\feedme\fields;

use verbb\feedme\base\Field;
use verbb\feedme\base\FieldInterface;

use Craft;

use Cake\Utility\Hash;

class DefaultField extends Field implements FieldInterface
{
    // Properties
    // =========================================================================

    public static $name = 'Default';
    public static $class = 'craft\fields\Default';


    // Templates
    // =========================================================================

    public function getMappingTemplate()
    {
        return 'feed-me/_includes/fields/default';
    }


    // Public Methods
    // =========================================================================

    public function parseField()
    {
        $value = $this->fetchValue();

        // Default fields expect strings, if its an array for an odd reason, serialise it
        if (is_array($value)) {
            if (empty($value)) {
                $value = '';
            } else {
                $value = json_encode($value);
            }
        }

        // Lastly, get each field to prepare values how they should
        $value = $this->field->serializeValue($this->field->normalizeValue($value));

        return $value;
    }
}