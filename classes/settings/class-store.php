<?php

namespace Comment_Notifications\Settings;

abstract class Store {
	abstract public function id(): string;

	abstract public function get();

	abstract public function set( $value );

	abstract public function delete();
}
