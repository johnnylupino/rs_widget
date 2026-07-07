<?php
/**
 * Configuration form for the User Widget block.
 * Compatible with Moodle 4.5+ (PHP 8.1+)
 */

defined('MOODLE_INTERNAL') || die();

class block_user_widget_edit_form extends block_edit_form {

    /**
     * Definiuje specyficzne pola konfiguracji dla tego bloku.
     * Moodle automatycznie wywołuje tę metodę podczas renderowania ustawień.
     *
     * @param MoodleQuickForm $mform
     */
    protected function specific_definition($mform) {
        global $DB;

        // Nagłówek sekcji konfiguracji
        $mform->addElement('header', 'configheader', get_string('blocksettings', 'block'));

        // Przygotowanie opcji: standardowe pola profilu użytkownika
        $options = [
            ''             => get_string('choose'),
            'department'   => get_string('department'),
            'institution'  => get_string('institution'),
            'city'         => get_string('city'),
            'country'      => get_string('country'),
            'idnumber'     => get_string('idnumber'),
            'email'        => get_string('email'),
        ];

        // Dynamiczne pobieranie pól niestandardowych (custom profile fields) z bazy danych Moodle
        try {
            $customfields = $DB->get_records('user_info_field', null, 'sortorder ASC', 'shortname, name');
            foreach ($customfields as $field) {
                $options['custom_field_' . $field->shortname] = format_string($field->name) . ' (Custom)';
            }
        } catch (Exception $e) {
            // W razie błędu bazy danych, kontynuujemy tylko z polami standardowymi
        }

        // Generowanie 5 list rozwijanych (dropdown) do mapowania w widżecie
        for ($i = 1; $i <= 5; $i++) {
            $mform->addElement('select', 'config_field' . $i, get_string('fieldrow', 'block_user_widget', $i), $options);
            $mform->setDefault('config_field' . $i, '');
            $mform->setType('config_field' . $i, PARAM_ALPHANUMEXT);
        }
    }
}