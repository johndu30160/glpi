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

/**
 * @var DB $DB
 * @var Migration $migration
 */

$default_charset = DBConnection::getDefaultCharset();
$default_collation = DBConnection::getDefaultCollation();
$default_key_sign = DBConnection::getDefaultPrimaryKeySignOption();

if (!$DB->tableExists('glpi_changesatisfactions')) {
    $query = "CREATE TABLE `glpi_changesatisfactions` (
        `id` int {$default_key_sign} NOT NULL AUTO_INCREMENT,
        `changes_id` int {$default_key_sign} NOT NULL DEFAULT '0',
        `type` int NOT NULL DEFAULT '1',
        `date_begin` timestamp NULL DEFAULT NULL,
        `date_answered` timestamp NULL DEFAULT NULL,
        `satisfaction` int DEFAULT NULL,
        `comment` text,
        PRIMARY KEY (`id`),
        UNIQUE KEY `changes_id` (`changes_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET={$default_charset} COLLATE={$default_collation} ROW_FORMAT=DYNAMIC;";
    $DB->queryOrDie($query, "10.1.0 add table glpi_changesatisfactions");
}

// Register crontask
CronTask::register('Change', 'createinquest', 86400, [
    'state'     => CronTask::STATE_WAITING,
    'mode'      => CronTask::MODE_INTERNAL
]);

// Add new entity config columns
if (!$DB->fieldExists('glpi_entities', 'max_closedate_change')) {
    $migration->addField('glpi_entities', 'max_closedate_change', 'timestamp', [
        'after' => 'inquest_URL',
        'null'  => true
    ]);
}
if (!$DB->fieldExists('glpi_entities', 'inquest_config_change')) {
    $migration->addField('glpi_entities', 'inquest_config_change', 'integer', [
        'after' => 'max_closedate_change',
        'value' => -2
    ]);
}
if (!$DB->fieldExists('glpi_entities', 'inquest_rate_change')) {
    $migration->addField('glpi_entities', 'inquest_rate_change', 'integer', [
        'after' => 'inquest_config_change',
        'value' => 0
    ]);
}
if (!$DB->fieldExists('glpi_entities', 'inquest_delay_change')) {
    $migration->addField('glpi_entities', 'inquest_delay_change', 'integer', [
        'after' => 'inquest_rate_change',
        'value' => -10
    ]);
}
if (!$DB->fieldExists('glpi_entities', 'inquest_URL_change')) {
    $migration->addField('glpi_entities', 'inquest_URL_change', 'string', [
        'after' => 'inquest_delay_change',
        'null'  => true
    ]);
}
if (!$DB->fieldExists('glpi_entities', 'inquest_duration_change')) {
    $migration->addField('glpi_entities', 'inquest_duration_change', 'integer', [
        'after' => 'inquest_duration',
        'value' => 0
    ]);
}

// Add new notifications

if (countElementsInTable('glpi_notifications', ['itemtype' => 'Change', 'event' => 'satisfaction']) === 0) {
    $DB->insertOrDie(
        'glpi_notificationtemplates',
        [
            'name'            => 'Change Satisfaction',
            'itemtype'        => 'Change',
            'date_mod'        => new \QueryExpression('NOW()'),
        ],
        'Add change satisfaction survey notification template'
    );
    $notificationtemplate_id = $DB->insertId();

    $DB->insertOrDie(
        'glpi_notificationtemplatetranslations',
        [
            'notificationtemplates_id' => $notificationtemplate_id,
            'language'                 => '',
            'subject'                  => '##change.action## ##change.title##',
            'content_text'             => <<<PLAINTEXT
##lang.change.title## : ##change.title##

##lang.change.closedate## : ##change.closedate##

##lang.satisfaction.text## ##change.urlsatisfaction##
PLAINTEXT
            ,
            'content_html'             => <<<HTML
&lt;p&gt;##lang.change.title## : ##change.title##&lt;/p&gt;
&lt;p&gt;##lang.change.closedate## : ##change.closedate##&lt;/p&gt;
&lt;p&gt;##lang.satisfaction.text## &lt;a href="##change.urlsatisfaction##"&gt;##change.urlsatisfaction##&lt;/a&gt;&lt;/p&gt;
HTML
            ,
        ],
        'Add change satisfaction survey notification template translations'
    );

    $notifications_data = [
        [
            'event' => 'satisfaction',
            'name'  => 'Change Satisfaction',
        ],
        [
            'event' => 'replysatisfaction',
            'name'  => 'Change Satisfaction Answer',
        ]
    ];
    foreach ($notifications_data as $notification_data) {
        $DB->insertOrDie(
            'glpi_notifications',
            [
                'name'            => $notification_data['name'],
                'entities_id'     => 0,
                'itemtype'        => 'Change',
                'event'           => $notification_data['event'],
                'comment'         => null,
                'is_recursive'    => 1,
                'is_active'       => 1,
                'date_creation'   => new \QueryExpression('NOW()'),
                'date_mod'        => new \QueryExpression('NOW()'),
            ],
            'Add change satisfaction survey notification'
        );
        $notification_id = $DB->insertId();

        $DB->insertOrDie(
            'glpi_notifications_notificationtemplates',
            [
                'notifications_id'         => $notification_id,
                'mode'                     => Notification_NotificationTemplate::MODE_MAIL,
                'notificationtemplates_id' => $notificationtemplate_id,
            ],
            'Add change satisfaction survey notification template instance'
        );

        $DB->insertOrDie(
            'glpi_notificationtargets',
            [
                'items_id'         => 3,
                'type'             => 1,
                'notifications_id' => $notification_id,
            ],
            'Add change satisfaction survey notification targets'
        );

        if ($notification_data['event'] === 'replysatisfaction') {
            $DB->insertOrDie(
                'glpi_notificationtargets',
                [
                    'items_id'         => 2,
                    'type'             => 1,
                    'notifications_id' => $notification_id,
                ],
                'Add change satisfaction survey notification targets'
            );
        }
    }
}
