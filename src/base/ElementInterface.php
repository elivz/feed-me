<?php
namespace verbb\feedme\base;

use craft\base\ComponentInterface;

interface ElementInterface extends ComponentInterface
{
    // Public Methods
    // =========================================================================
    
    public function getGroupsTemplate();
    
    public function getColumnTemplate();
    
    public function getMappingTemplate();

    public function getGroups();

    public function getQuery($settings, $params = []);

    public function setModel($settings);

    public function matchExistingElement($data, $settings);

    public function delete($elementIds);

    public function disable($elementIds);

    public function save($data, $settings);

    public function afterSave($data, $settings);

}
