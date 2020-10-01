<?php

// don't load directly
if ( ! defined( 'ABSPATH' ) ) {
	die();
}

/*
Plugin Name: Gravity Forms iContact Add-On
Plugin URI: https://gravityforms.com
Description: Integrates Gravity Forms with iContact, allowing form submissions to be automatically sent to your iContact account.
Version: 1.5
Author: Gravity Forms
Author URI: https://gravityforms.com
License: GPL-2.0+
Text Domain: gravityformsicontact
Domain Path: /languages

------------------------------------------------------------------------
Copyright 2009-2020 rocketgenius


This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
*/

define( 'GF_ICONTACT_VERSION', '1.5' );

add_action( 'gform_loaded', array( 'GF_iContact_Bootstrap', 'load' ), 5 );

class GF_iContact_Bootstrap {

	public static function load(){
		require_once( 'class-gf-icontact.php' );
		GFAddOn::register( 'GFiContact' );
	}

}

function gf_icontact() {
	return GFiContact::get_instance();
}
