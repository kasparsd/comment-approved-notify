<?php

namespace Comment_Notifications\Settings;

class Field_Textarea extends Field {

	public function sanitize( $value ): ?string {
		if ( ! isset( $value ) ) {
			return null;
		}

		$value = trim( $value );

		if ( '' === $value ) {
			return null;
		}

		return sanitize_textarea_field( $value );
	}

	public function render(): string {
		$parts = [];

		$parts[] = sprintf(
			'<textarea type="text" name="%s" placeholder="%s" rows="%s" %s class="large-text" />%s</textarea>',
			esc_attr( $this->name() ),
			esc_attr( $this->setting( 'placeholder' ) ),
			esc_attr( $this->setting( 'rows' ) ),
			disabled( $this->is_disabled(), true, false ),
			esc_textarea( $this->get() )
		);

		$help = $this->help();

		if ( ! empty( $help ) ) {
			$parts[] = sprintf( '<p class="description">%s</p>', wp_kses_post( $help ) );
		}

		return implode( '', $parts );
	}
}
