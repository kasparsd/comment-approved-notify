<?php

namespace Comment_Notifications\Settings;

class Store_Option extends Store {

	protected string $option_name;

	public function __construct( string $option_name ) {
		$this->option_name = preg_replace( '/[\s\.]/', '_', $option_name ); // PHP replaces all HTML input dots and spaces with underscores.
	}

	public function id(): string {
		return $this->option_name;
	}

	public function get() {
		$value = get_option( $this->option_name );

		// Return null if the option does not exist.
		if ( false === $value || '' === $value ) {
			return null;
		}

		return $value;
	}

	public function set( $value ) {
		return update_option( $this->option_name, $value );
	}

	public function delete() {
		return delete_option( $this->option_name );
	}
}
