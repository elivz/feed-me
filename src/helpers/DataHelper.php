<?php
namespace verbb\feedme\helpers;

use Craft;

use verbb\feedme\FeedMe;

use Cake\Utility\Hash;

class DataHelper
{
    // Public Methods
    // =========================================================================

    public static function fetchSimpleValue($feedData, $fieldInfo, $element = null)
    {
        $node = Hash::get($fieldInfo, 'node');
        $default = Hash::get($fieldInfo, 'default');
        $value = Hash::get($feedData, $node);

        // Use the default value for the field-mapping (if defined)
        if ($value === null || $value === '') {
            $value = $default;
        }

        return $value;
    }

    public static function fetchArrayValue($feedData, $fieldInfo, $element = null)
    {
        $value = [];

        $node = Hash::get($fieldInfo, 'node');
        $default = Hash::get($fieldInfo, 'default');

        $dataDelimiter = FeedMe::$plugin->service->getConfig('dataDelimiter');

        // Some fields require array, or multiple values like Elements, Checkboxes, etc, and we need to parse them differently.
        // Firstly, field mapping is setup like `MatrixBlock/Images` but actual feed is structured like `MatrixBlock/0/Images/0`.
        // We strip out the numbers to first find the node we've mapped to, then iterate over possible multiple values in the feed.
        foreach ($feedData as $nodePath => $nodeValue) {
            // Strip out array numbers in the feed path like: MatrixBlock/0/Images/0. We use this to get the field
            // its supposed to match up with, which is stored in the DB like MatrixBlock/Images
            $feedPath = preg_replace('/(\/\d+\/)/', '/', $nodePath);
            $feedPath = preg_replace('/^(\d+\/)|(\/\d+)/', '', $feedPath);
            
            if ($feedPath == $node || $nodePath == $node) {
                if ($nodeValue === null || $nodeValue === '') {
                    $nodeValue = $default;
                }

                // Allow pipes '|' to denote multiple items, but even if it doesn't contain one, explode will create
                // an array, so ensure to merge with the current results.
                if (is_string($nodeValue) && strpos($nodeValue, $dataDelimiter) !== false) {
                    $value = array_merge($value, explode($dataDelimiter, $nodeValue));
                } else {
                    $value[] = $nodeValue;
                }
            }
        }

        // Check if not importing, just using default
        if ($node === 'usedefault' && !$value) {
            $value = $default;
        }

        return $value;
    }

    public static function fetchValue($feedData, $fieldInfo, $element = null)
    {
        $value = [];

        $node = Hash::get($fieldInfo, 'node');
        $default = Hash::get($fieldInfo, 'default');

        $dataDelimiter = FeedMe::$plugin->service->getConfig('dataDelimiter');

        // Some fields require array, or multiple values like Elements, Checkboxes, etc, and we need to parse them differently.
        // Firstly, field mapping is setup like `MatrixBlock/Images` but actual feed is structured like `MatrixBlock/0/Images/0`.
        // We strip out the numbers to first find the node we've mapped to, then iterate over possible multiple values in the feed.
        foreach ($feedData as $nodePath => $nodeValue) {
            // Strip out array numbers in the feed path like: MatrixBlock/0/Images/0. We use this to get the field
            // its supposed to match up with, which is stored in the DB like MatrixBlock/Images
            $feedPath = preg_replace('/(\/\d+\/)/', '/', $nodePath);
            $feedPath = preg_replace('/^(\d+\/)|(\/\d+)/', '', $feedPath);
            
            if ($feedPath == $node || $nodePath == $node) {
                if ($nodeValue === null || $nodeValue === '') {
                    $nodeValue = $default;
                }

                // Allow pipes '|' to denote multiple items, but even if it doesn't contain one, explode will create
                // an array, so ensure to merge with the current results.
                if (is_string($nodeValue) && strpos($nodeValue, $dataDelimiter) !== false) {
                    $value = array_merge($value, explode($dataDelimiter, $nodeValue));
                } else {
                    $value[] = $nodeValue;
                }
            }
        }

        // Help to normalise things if an array with only one item. Probably a better idea to offload this to each
        // attribute of field definition, as its quite an assumption at this point...
        if (count($value) === 1) {
            $value = $value[0];
        }

        // Check if not importing, just using default
        if ($node === 'usedefault' && !$value) {
            $value = $default;
        }

        return $value;
    }

    public static function parseFieldDataForElement($value, $element)
    {
        if (is_string($value) && strpos($value, '{') !== false) {
            // Make sure to wrap in try/catch, as if this is a literal '{' in content somewhere
            // it won't be a field handle tag, causing the Twig Lexer to freak out. We ignore those errors
            try {
                $value = Craft::$app->getView()->renderObjectTemplate($value, $element);
            } catch (\Throwable $e) {
                
            }
        }

        return $value;
    }

    public static function compareElementContent($content, $element)
    {
        if (!$element) {
            return false;
        }

        $trackedChanges = $content;

        $fields = $element->getSerializedFieldValues();
        $attributes = $element->attributes;

        foreach ($content as $key => $newValue) {
            $existingValue = Hash::get($fields, $key);

            // Check for simple fields first
            if ($existingValue === $newValue) {
                unset($trackedChanges[$key]);
                continue;
            }

            // Then check for simple attributes
            $existingValue = Hash::get($attributes, $key);

            if ($existingValue === $newValue) {
                unset($trackedChanges[$key]);
                continue;
            }

            // Check for complicated fields = looking at you Matrix!
            $existingValue = Hash::get($fields, $key);

            // For debugging - clearly see how the data differs
            if (is_array($existingValue) && is_array($newValue)) {
                $diff = self::arrayCompare($existingValue, $newValue);

                FeedMe::debug($key . ' - diff');
                FeedMe::debug($diff);
            }

            FeedMe::debug($key . ' - existing');
            FeedMe::debug($existingValue);
            FeedMe::debug($key . ' - new');
            FeedMe::debug($newValue);

            FeedMe::info('Data to update for `{i}`: `{j}`.', ['i' => $key, 'j' => json_encode($newValue)]);
        }

        if (empty($trackedChanges)) {
            return true;
        }
    }

    public static function arrayCompare($array1, $array2)
    {
        $diff = false;
        
        foreach ($array1 as $key => $value) {
            if (!array_key_exists($key, $array2)) {
                $diff[0][$key] = $value;
            } elseif (is_array($value)) {
                if (!is_array($array2[$key])) {
                    $diff[0][$key] = $value;
                    $diff[1][$key] = $array2[$key];
                } else {
                    $new = self::arrayCompare($value, $array2[$key]);
                    
                    if ($new !== false) {
                        if (isset($new[0])) {
                            $diff[0][$key] = $new[0];
                        }

                        if (isset($new[1])) {
                            $diff[1][$key] = $new[1];
                        }
                    }
                }
            } elseif ($array2[$key] !== $value) {
                $diff[0][$key] = $value;
                $diff[1][$key] = $array2[$key];
            }
        }

        foreach ($array2 as $key => $value) {
            if (!array_key_exists($key, $array1)) {
                $diff[1][$key] = $value;
            }
        }

        return $diff;
    }

}
