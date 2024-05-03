<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Strings for component 'block_data_cart', language 'fr'
 *
 * @package   block_data_cart
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['configtitle'] = 'Titre du bloc';
$string['configdocumentproducerbinding'] = 'Choix du générateur de document';
$string['data_cart:addinstance'] = 'Ajouter un bloc "Panier de données"';
$string['newblock'] = '(Nouveau Panier de données)';
$string['pluginname'] = 'Panier de données';
$string['export'] = 'Exporter';
$string['anonexport'] = 'Exporter anonyme';
$string['reset'] = 'Réinitialiser';
$string['norecords'] = 'Pas de données dans le panier';
$string['configanonymize'] = 'Anonymiser les fiches';
$string['configanonymize_help'] = 'Si cette option est active, les noms, prénoms et email des fiches seront occultées.';
$string['configlistfields'] = 'Champs d\'identification de fiche';
$string['configlistfields_help'] = 'Donner la liste (avec des espaces) des noms de champs qui seront utilisés pour le nom de la fiche dans le panier';
$string['configsensiblefields'] = 'Champs de données sensibles';
$string['configsensiblefields_help'] = 'Donner la liste (à virgule) des noms de champs qui doivent être occultés lors de l\'export';
$string['privacy:metadata:block'] = 'Le bloc Data Cart ne détient aucune données personnelles.';

$string['configdocumentproducerbinding_help'] = 'Un générateur de document doit être associé au panier de données p our produire une
version exportable des données. Actuellement, seul le module PDCertificate d\'APL dispose de l\'API attendue.';
