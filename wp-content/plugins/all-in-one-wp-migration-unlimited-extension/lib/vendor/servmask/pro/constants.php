<?php
/**
 * Copyright (C) 2014-2023 ServMask Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * ███████╗███████╗██████╗ ██╗   ██╗███╗   ███╗ █████╗ ███████╗██╗  ██╗
 * ██╔════╝██╔════╝██╔══██╗██║   ██║████╗ ████║██╔══██╗██╔════╝██║ ██╔╝
 * ███████╗█████╗  ██████╔╝██║   ██║██╔████╔██║███████║███████╗█████╔╝
 * ╚════██║██╔══╝  ██╔══██╗╚██╗ ██╔╝██║╚██╔╝██║██╔══██║╚════██║██╔═██╗
 * ███████║███████╗██║  ██║ ╚████╔╝ ██║ ╚═╝ ██║██║  ██║███████║██║  ██╗
 * ╚══════╝╚══════╝╚═╝  ╚═╝  ╚═══╝  ╚═╝     ╚═╝╚═╝  ╚═╝╚══════╝╚═╝  ╚═╝
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Kangaroos cannot jump here' );
}

// ================
// = Package path =
// ================
if ( ! defined( 'AI1WMVE_PATH' ) ) {
	define( 'AI1WMVE_PATH', dirname( __FILE__ ) );
}

// ===================
// = Controller Path =
// ===================
if ( ! defined( 'AI1WMVE_CONTROLLER_PATH' ) ) {
	define( 'AI1WMVE_CONTROLLER_PATH', AI1WMVE_PATH . DIRECTORY_SEPARATOR . 'controller' );
}

// ==============
// = Model Path =
// ==============
if ( ! defined( 'AI1WMVE_MODEL_PATH' ) ) {
	define( 'AI1WMVE_MODEL_PATH', AI1WMVE_PATH . DIRECTORY_SEPARATOR . 'model' );
}

// =============
// = View Path =
// =============
if ( ! defined( 'AI1WMVE_TEMPLATES_PATH' ) ) {
	define( 'AI1WMVE_TEMPLATES_PATH', AI1WMVE_PATH . DIRECTORY_SEPARATOR . 'view' );
}

// ===========================
// = Purchase Activation URL =
// ===========================
if ( ! defined( 'AI1WMVE_PURCHASE_ACTIVATION_URL' ) ) {
	define( 'AI1WMVE_PURCHASE_ACTIVATION_URL', 'https://servmask.com/purchase/activations' );
}

// ======================
// = ServMask Stats URL =
// ======================
if ( ! defined( 'AI1WMVE_STATS_URL' ) ) {
	define( 'AI1WMVE_STATS_URL', 'https://servmask.com/api/stats' );
}

// =================
// = Max File Size =
// =================
if ( ! defined( 'AI1WMVE_MAX_FILE_SIZE' ) ) {
	define( 'AI1WMVE_MAX_FILE_SIZE', 0 );
}

// ============================
// = Schedules Events Options =
// ============================
if ( ! defined( 'AI1WMVE_SCHEDULES_OPTIONS' ) ) {
	define( 'AI1WMVE_SCHEDULES_OPTIONS', 'ai1wmve_schedule_events' );
}
