<?php

/**
 * ---------------------------------------------------------------------
 * GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2015-2022 Teclib' and contributors.
 *
 * http://glpi-project.org
 *
 * based on GLPI - Gestionnaire Libre de Parc Informatique
 * Copyright (C) 2003-2014 by the INDEPNET Development Team.
 *
 * ---------------------------------------------------------------------
 *
 * LICENSE
 *
 * This file is part of GLPI.
 *
 * GLPI is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * GLPI is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with GLPI. If not, see <http://www.gnu.org/licenses/>.
 * ---------------------------------------------------------------------
 */

namespace tests\units;

use DbTestCase;

abstract class CommonITILSatisfaction extends DbTestCase
{

    /**
     * Return the name of the class this test class tests
     * @return string
     */
    protected function getTestedClass(): string
    {
        $test_class = static::class;
        // Rule class has the same name as the test class but in the global namespace
        return substr(strrchr($test_class, '\\'), 1);
    }

    public function testGetItemtype()
    {
        /** @var \CommonITILSatisfaction $tested_class */
        $tested_class = $this->getTestedClass();
        $itemtype = $tested_class::getItemtype();
        // Verify the itemtype is a subclass of CommonITILObject
        $this->boolean(is_a($itemtype, \CommonITILObject::class, true))->isTrue();
    }

//    public function testGetSurveyUrl()
//    {
//        /** @var \CommonITILSatisfaction $tested_class */
//        $tested_class = $this->getTestedClass();
//        $itemtype = $tested_class::getItemtype();
//        $item = new $itemtype();
//        $tag_prefix = strtoupper($item::getType());
//        $items_id = $item->add([
//            'name' => 'testGetSurveyUrl',
//            'content' => 'testGetSurveyUrl',
//            'entities_id' => getItemByTypeName('Entity', '_test_root_entity', true)
//        ]);
//        $this->integer($items_id)->isGreaterThan(0);
//
//        // Set inquest_URL for root entity
//        $entity = new \Entity();
//        $entity->getFromDB(getItemByTypeName('Entity', '_test_root_entity', true));
//        $original_inquest_url = $entity->fields['inquest_URL'];
//        $inquest_url = "[ITEMTYPE],[ITEMTTYPE_NAME],[{$tag_prefix}_ID],[{$tag_prefix}_NAME],[{$tag_prefix}_CREATEDATE],
//        [{$tag_prefix}_SOLVEDATE],[{$tag_prefix}_PRIORITY]";
//        $entity->update([
//            'id'                => $entity->getID(),
//            'inquest_URL'       => $inquest_url,
//            'inquest_config'    => \CommonITILSatisfaction::TYPE_EXTERNAL,
//        ]);
//        $this->login();
//
//        $expected = "{$itemtype},{$item->getTypeName()},{$items_id},{$item->fields['name']},{$item->getField('date')},
//            {$item->getField('solvedate')},{$item->getField('priority')}";
//        $generated = \Entity::generateLinkSatisfaction($item);
//        $this->string($generated)->isEqualTo($expected);
//
//        // Restore original value
//        $entity->update([
//            'id' => $entity->getID(),
//            'inquest_URL' => $original_inquest_url,
//            'inquest_config' => -2,
//        ]);
//    }
}
