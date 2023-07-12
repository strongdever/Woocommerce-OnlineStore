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

// Include constants
require_once dirname( __FILE__ ) .
	DIRECTORY_SEPARATOR .
	'constants.php';

// Include exceptions
require_once dirname( __FILE__ ) .
	DIRECTORY_SEPARATOR .
	'exceptions.php';

require_once AI1WMVE_CONTROLLER_PATH .
	DIRECTORY_SEPARATOR .
	'class-ai1wmve-main-controller.php';

require_once AI1WMVE_CONTROLLER_PATH .
	DIRECTORY_SEPARATOR .
	'class-ai1wmve-export-controller.php';

require_once AI1WMVE_CONTROLLER_PATH .
	DIRECTORY_SEPARATOR .
	'class-ai1wmve-schedules-controller.php';

require_once AI1WMVE_MODEL_PATH .
	DIRECTORY_SEPARATOR .
	'schedule' .
	DIRECTORY_SEPARATOR .
	'class-ai1wmve-schedule-events.php';

require_once AI1WMVE_MODEL_PATH .
	DIRECTORY_SEPARATOR .
	'schedule' .
	DIRECTORY_SEPARATOR .
	'class-ai1wmve-schedule-event.php';

require_once AI1WMVE_MODEL_PATH .
	DIRECTORY_SEPARATOR .
	'schedule' .
	DIRECTORY_SEPARATOR .
	'class-ai1wmve-schedule-event-log.php';
