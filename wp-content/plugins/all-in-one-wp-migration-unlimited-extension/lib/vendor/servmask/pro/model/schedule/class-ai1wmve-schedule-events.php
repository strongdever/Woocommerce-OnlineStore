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

if ( ! class_exists( 'Ai1wmve_Schedule_Events' ) ) {
	class Ai1wmve_Schedule_Events {


		protected $events = array();

		public function __construct() {
			$this->events = get_option( AI1WMVE_SCHEDULES_OPTIONS, false );
			if ( false === $this->events ) {
				$this->create_default_events();
			}
		}

		/**
		 * Get all events
		 *
		 * @return array
		 */
		public function all() {
			return $this->events;
		}

		public function enabled() {
			return array_filter(
				$this->all(),
				function ( $event ) {
					return $event->is_enabled();
				}
			);
		}

		public function save( $data ) {
			if ( empty( $data['event_id'] ) ) {
				$data['event_id'] = time();
			}
			$event = new Ai1wmve_Schedule_Event( $data );
			$event->clear_schedule()
				->create_schedule();
			$this->events[ $event->event_id() ] = $event;

			return $this->update_option();
		}

		public function find( $id ) {
			if ( isset( $this->events[ $id ] ) ) {
				return $this->events[ $id ];
			}

			return false;
		}

		public function find_or_new( $id ) {
			if ( $event = $this->find( $id ) ) {
				return $event;
			}

			return new Ai1wmve_Schedule_Event( array() );
		}

		public function delete( $id ) {
			if ( isset( $this->events[ $id ] ) && ( $event = $this->find( $id ) ) ) {
				$event->clear_schedule()
					->delete_data();
				unset( $this->events[ $id ] );

				return $this->update_option();
			}

			throw new Ai1wmve_Schedules_Exception( __( 'Unable to find scheduled event', AI1WM_PLUGIN_NAME ) );
		}

		protected function update_option() {
			return update_option( AI1WMVE_SCHEDULES_OPTIONS, $this->events );
		}

		protected function create_default_events() {
			$this->events = array();

			$this->save(
				array(
					'title'        => __( 'Daily backup' ),
					'type'         => Ai1wmve_Schedule_Event::TYPE_EXPORT,
					'status'       => Ai1wmve_Schedule_Event::STATUS_DISABLED,
					'schedule'     => array(
						'interval' => Ai1wmve_Schedule_Event::INTERVAL_DAILY,
						'hour'     => 0,
						'minute'   => 0,
					),
					'notification' => array(
						'reminder' => Ai1wmve_Schedule_Event::REMINDER_NONE,
					),
				)
			);
		}
	}
}
