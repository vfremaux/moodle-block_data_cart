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
 * Javascript controller for controlling the sections.
 *
 * @module     block_multicourse_navigation/collapse_control
 * @package    block_multicourse_navigation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// jshint unused: true, undef:true
define(['jquery', 'core/log', 'core/config'], function ($, log, cfg) {

    var datacart = {

        blockid: 0,

        init: function (params) {
            datacart.blockid = params[0];
            // Use defered binding.
            $('.datacart-list').on('click', '.datacart-delete', this.remove_record);
            $('.datacart-list').on('click', '#datacart-reset', this.reset_list);

            log.debug("AMD block data cart " + datacart.blockid + " initialized !")
        },

        /**
         * This function is called from an event handler
         * @see cvtheque.js in data_behaviour
         */
        add_record: function (recordid) {

            var url = cfg.wwwroot + '/blocks/data_cart/ajax/service.php';
            url += '?what=addrecord';
            url += '&blockid=' + datacart.blockid;
            url += '&recordid=' + recordid;

            $.get(url);

            datacart.reload_list();
        },

        /**
         * This function is attached to delete buttons in datacart list.
         */
        remove_record: function (e) {
            e.preventDefault();

            var that, url;
            that = $(this);

            url = cfg.wwwroot + '/blocks/data_cart/ajax/service.php';
            url += '?what=removerecord';
            url += '&blockid=' + datacart.blockid;
            url += '&recordid=' + that.attr('data-recordid');

            $.get(url);

            datacart.reload_list();
        },

        /**
         * This function is attached to reset buttons in datacart list.
         * Will reset cart for the current user.
         */
        reset_list: function (e) {
            e.preventDefault();

            var that, url;
            that = $(this);

            url = cfg.wwwroot + '/blocks/data_cart/ajax/service.php';
            url += '?what=reset';
            url += '&blockid=' + datacart.blockid;

            $.get(url);

            datacart.reload_list();
        },

        reload_list: function() {

            var url = cfg.wwwroot + '/blocks/data_cart/ajax/service.php';
            url += '?what=reload';
            url += '&blockid=' + datacart.blockid;

            $.get(url, function(data) {
                $('#listcontent-block-' + datacart.blockid).html(data);
            }, 'html');
        }

    };

    return datacart;
});

