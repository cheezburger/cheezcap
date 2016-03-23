<?php
/**
 * CheezCap - Cheezburger Custom Administration Panel
 * (c) 2008 - 2011 Cheezburger Network (Pet Holdings, Inc.)
 * LOL: http://cheezburger.com
 * Source: http://github.com/cheezburger/cheezcap/
 * Authors: Kyall Barrows, Toby McKes, Stefan Rusek, Scott Porad
 * UnLOLs by Mo Jangda (batmoo@gmail.com)
 * License: GNU General Public License, version 2 (GPL), http://www.gnu.org/licenses/gpl-2.0.html
 */

class CheezCapGroup {
	var $name;
	var $id;
	var $options;

	function __construct( $_name, $_id, $_options ) {
		$this->name = $_name;
		$this->id = "cap_$_id";
		$this->options = $_options;
	}

	function write_html() {
		?>
		<table class="form-table" width="100%">
			<tr valign="top">
				<th scope="row"><?php _e( 'Option', 'cheezcap' ); ?></th>
				<th scope="row"><?php _e( 'Value', 'cheezcap' ); ?></th>
			</tr>
			<?php
			for ( $i=0; $i < count( $this->options ); $i++ ) {
				$this->options[$i]->write_html();
			}
			?>
		</table>
		<?php
	}
}

class CheezCapOption {
	var $name;
	var $desc;
	var $id;
	var $_key;
	var $std;
	var $validation_cb;

	function __construct( $_name, $_desc, $_id, $_std, $_validation_cb = false ) {
		$this->name = $_name;
		$this->desc = $_desc;
		$this->id = "cap_$_id";
		$this->_key = $_id;
		$this->std = $_std;
		if ( $_validation_cb && is_callable( $_validation_cb ) ) {
			$this->validation_cb = $_validation_cb;
		}
	}

	function write_html() {
	}

	function update( $ignored = '' ) {
		$value = isset( $_POST[$this->id] ) ? $_POST[$this->id] : '';
		$this->save( $value );
	}

	function reset( $ignored = '' ) {
		$this->save( $this->std );
	}

	function import( $data ) {
		if ( array_key_exists( $this->id, $data->dict ) )
			$this->save( $data->dict[$this->id] );
	}

	function export( $data ) {
		$data->dict[$this->id] = get_option( $this->id );
	}

	function save( $value ) {
		if ( $this->validation_cb )
			$value = call_user_func($this->validation_cb, $this->id, $value);
		else
			$value = stripslashes_deep( $value );
		update_option( $this->id, $value );
	}

	function get() {
		return get_option( $this->id );
	}
}

/**
 * Adds support for selecting any media libarary
 * content. If image, the URL will be saved, otherwise
 * the attachment Id will be saved in wp_options
 * 
 */
class CheezCapMediaOption extends CheezCapOption {
	var $options;

	function __construct( $_name, $_desc, $_id, $_std = ''  ) {
		parent::__construct( $_name, $_desc, $_id, $_std );
	}

	function write_html() {

		// Pre-reqs for loading the WP media-upload modal
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'media-upload' );
		wp_enqueue_script( 'thickbox' );
		wp_enqueue_style( 'thickbox' );
		wp_enqueue_media();

		$is_img = false;

		// Populate the default option or the saved one
		$stdText = $this->std;
		$stdTextOption = get_option( $this->id );

		// User chose an image
		if ( ! empty( $stdTextOption ) && (int) $stdTextOption == 0 ) {
			$stdText = $stdTextOption;
			$is_img = true;
		}

		// User chose a non-image file
		else {

			$stdText = false;

			// Get the attachment object
			$attach = get_post( (int) $stdTextOption );
			
			// Extract filename
			if ( isset( $attach->guid ) ) {
				$guid = $attach->guid;
				$arr = explode( '/', $guid );
				$stdText = $arr[ count( $arr ) - 1 ];
			}
		}	

		?>

		<style>
			.media-label {
				position: relative;
			}

			.delete-btn.button {
				padding-top: 3px;
				line-height: 1em;
			}

			.delete-btn > span {
				line-height: initial;
			}

			.button.cap-media-btn {
				margin-top: 10px;
				display: block;
			}

			img,
			.cap-media-btn {
				width: 250px;
				min-width: 150px;
			}
		</style>

		<tr valign="top">
			<th scope="row"><?php echo esc_html( $this->name ); ?></th>
			<td>
				<label class='media-label' for="<?php echo esc_attr( $this->id ); ?>">

					<input type="hidden" id="<?php echo esc_attr( $this->id ); ?>" name="<?php echo esc_attr( $this->id ); ?>" value="<?php echo esc_attr( $stdText ); ?>" size="40" />
					
					<div id="<?php echo esc_attr( 'text_' . $this->id ); ?>" class='text-display <?php echo ( $is_img || $stdText == false ) ? 'hidden' : ''; ?>' >
						<i class='dashicons dashicons-media-text'></i><span><?php echo esc_html( $stdText ); ?></span>
					</div>
					
					<img id="<?php echo esc_attr( 'img_'  . $this->id ); ?>" src="<?php echo ( $is_img ) ? esc_url( $stdText ) : ''; ?>" />
					
					<input type="button" class='button button-primary cap-media-btn' id="<?php echo esc_attr( 'btn_' . $this->id ); ?>" value="Open Media Library" />
					
					<button class='delete-btn button button-default cap-media-btn <?php echo ( !empty( $stdText ) ) ? '' : 'hidden'; ?>' id='<?php echo esc_attr( 'delete_' . $this->id ); ?>'>Clear Item</button>
				</label>
			</td>
		</tr>

		<tr valign="top">
			<td colspan=2>
				<small><?php echo esc_html( $this->desc ); ?></small><hr />
			</td>
		</tr>

		<script>
		(function($) {
			$( document ).ready( function() {

				/*
				 * Initialize the window object used to track which instance was changed
				 */
				if( typeof( window.cheezcap ) === 'undefined' ) {
					window.cheezcap = { mediaOptCbDefined : null, optionId : null };
				}

				/* 
				 * Register the watch for this instance
				 */
				$( '<?php echo esc_attr( '#btn_' . $this->id ); ?>' ).click( function() {

					// Update the window object so the callback knows what to do
					window.cheezcap.optionId = "<?php echo esc_attr( $this->id ); ?>";

					tb_show( '<?php echo esc_html( $this->name ); ?>', 'media-upload.php?type=image&amp;TB_iframe=true');

					return false;
				});

				/* 
				 * Register our "send_to_editor" function if it isn't already
				 */
				if( window.cheezcap.mediaOptCbDefined === null ) {

					// Make sure we don't redefine it
					window.cheezcap.mediaOptCbDefined = true;

					/**
					 * Callback fired by thickbox to update the elements referenced
					 * in the window.cheezcap object with the selected media item
					 * 
					 * @param  {obj} html - jQ object
					 */
					window.send_to_editor = function(html) {
						var isImg, filename, value, imgUrl;
						
						isImg = true;

						// Get the img url
						value = $( 'img', html ).attr( 'src' );

						// If it's not an img
						if( typeof value === 'undefined' ) {
							
							isImg = false;

							// Save the attachement Id
							value = $(html).attr( 'rel' ).replace( 'attachment wp-att-', '' );
							filename = $( html ).text();
						}

						// Update the actual value to be saved to wp_options
						$( 'input#' + window.cheezcap.optionId  ).val( value );

						// Show image preview and hide text display
						if( isImg ) {
							
							$( '#img_'  + window.cheezcap.optionId ).attr( 'src', value ).show();
							$( '#text_' + window.cheezcap.optionId + ' span' ).text( '' ).parent().addClass( 'hidden' );
						}

						// Show the text display and hide imaage element
						else {

							$( '#img_'  + window.cheezcap.optionId ).hide();
							$( '#text_' + window.cheezcap.optionId + ' span' ).text( filename ).parent().removeClass( 'hidden' );
						}

						// Show the delete button
						$( '<?php echo '#delete_' . $this->id; ?>' ).removeClass( 'hidden' );

						tb_remove();
					}
				}

				/*
				 * Remove the selected media item
				 */
				$( '<?php echo esc_attr( '#delete_' . $this->id ); ?>' ).click( function() {

					// Clear the image, the input field and hide the delete button
					$( '<?php echo '#img_' . $this->id; ?>' ).prop( 'src', '' );
					$( '<?php echo 'input#' . $this->id; ?>' ).prop( 'value', '' ).hide();
					$( '<?php echo '#text_' . $this->id . ' span'; ?>' ).text( '' ).parent().addClass( 'hidden' );
					$( '<?php echo '#delete_' . $this->id; ?>' ).addClass( 'hidden' );
					return false;
				}); 
			});
		})(jQuery);

		</script>
	<?php
	}

	function save( $value ) {
		parent::save( $value );
	}

	function get() {
		$value = get_option( $this->id, $this->std );
		if ( strtolower( $value ) == 'disabled' )
			return false;
		return $value;
	}
}

class CheezCapTextOption extends CheezCapOption {
	var $useTextArea;

	function __construct( $_name, $_desc, $_id, $_std = '', $_useTextArea = false, $_validation_cb = false ) {
		parent::__construct( $_name, $_desc, $_id, $_std, $_validation_cb );
		$this->useTextArea = $_useTextArea;
	}

	function save( $value ) {
		parent::save( $this->sanitize( $value ) );
	}

	function write_html() {
		$stdText = $this->std;

		$stdTextOption = get_option( $this->id );
		if ( ! empty( $stdTextOption ) )
			$stdText = $stdTextOption;

		?>
		<tr valign="top">
			<th scope="row"><label for="<?php echo $this->id; ?>"><?php echo esc_html( $this->name . ':' ); ?></label></th>
			<?php
			$commentWidth = 2;
			if ( $this->useTextArea ) :
				$commentWidth = 1; ?>
				<td rowspan="2">
					<textarea style="width:100%;height:100%;" name="<?php echo esc_attr( $this->id ); ?>" id="<?php echo esc_attr( $this->id ); ?>"><?php echo esc_textarea( $stdText ); ?></textarea>
			<?php else : ?>
				<td>
					<input name="<?php echo esc_attr( $this->id ); ?>" id="<?php echo esc_attr( $this->id ); ?>" type="text" value="<?php echo esc_attr( $stdText ); ?>" size="40" />
			<?php endif; ?>
			</td>
		</tr>
		<tr valign="top">
			<td colspan="<?php echo absint( $commentWidth ); ?>">
				<label for="<?php echo $this->id; ?>">
					<small><?php echo esc_html( $this->desc ); ?></small>
				</label>
			</td>
		</tr>
		<tr valign="top">
			<td colspan="2"><hr /></td>
		</tr>
		<?php
	}

	function sanitize( $value ) {
		if( $this->useTextArea )
			return wp_filter_post_kses( $value );
		else
			return strip_tags( $value );
	}

	function get() {
		$value = get_option( $this->id );
		if ( empty( $value ) )
			return $this->std;
		return $this->sanitize( $value );
	}
}

class CheezCapDropdownOption extends CheezCapOption {
	var $options;

	function __construct( $_name, $_desc, $_id, $_options, $_stdIndex = 0, $_options_labels = array(), $_validation_cb = false ) {
		$_std = ! isset( $_options[$_stdIndex] ) ? $_options[0] : $_options[$_stdIndex];
		parent::__construct( $_name, $_desc, $_id, $_std, $_validation_cb );
		$this->options = $_options;
		$this->options_labels = $_options_labels;
	}

	function save( $value ) {
		if( ! in_array( $value, $this->options ) )
			$this->reset();
		parent::save( $value );
	}

	function write_html() {
		?>
		<tr valign="top">
			<th scope="row"><label for="<?php echo $this->id; ?>"><?php echo esc_html( $this->name ); ?></label></th>
			<td>
				<select name="<?php echo esc_attr( $this->id ); ?>" id="<?php echo esc_attr( $this->id ); ?>">
				<?php $count = 0; ?>
				<?php foreach( $this->options as $option ) : ?>
					<?php $option_label = isset( $this->options_labels[$count] ) ? $this->options_labels[$count] : $option; ?>

					<option<?php selected( ( get_option( $this->id ) == $option || ( null === get_option( $this->id, null ) && $this->std == $option ) ) ) ?> value="<?php echo esc_attr( $option ); ?>"><?php echo esc_html( $option_label ); ?></option>

					<?php $count++; ?>
				<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr valign="top">
			<td colspan=2>
				<label for="<?php echo $this->id; ?>"><small><?php echo esc_html( $this->desc ); ?></small></label><hr />
			</td>
		</tr>
		<?php
	}

	function sanitize( $value ) {
		return strip_tags( $value );
	}

	function get() {
		$value = get_option( $this->id, $this->std );
		if ( strtolower( $value ) == 'disabled' )
			return false;
		return $this->sanitize( $value );
	}
}

class CheezCapBooleanOption extends CheezCapDropdownOption {
	var $default;

	function __construct( $_name, $_desc, $_id, $_default = false ) {
		$this->default = $_default;
		parent::__construct( $_name, $_desc, $_id, array( 0, 1 ), $_default ? 0 : 1, array( 'Disabled', 'Enabled' ) );
	}

	function get() {
		$value = get_option( $this->id, $this->default );
		if ( is_bool( $value ) )
			return $value;
		switch ( strtolower( $value ) ) {
			case 'true':
			case 'enable':
			case 'enabled':
			case '1':
			case 1:
				return true;
			default:
				return false;
		}
	}
}

class CheezCapMultipleCheckboxesOption extends CheezCapOption {
	var $options_checked;
	var $options;
	var $options_labels;

	function __construct( $_name, $_desc, $_id, $_options, $_options_labels = array(), $_options_checked, $_validation_cb = false ) {
		$this->options = $_options;
		$this->options_labels = $_options_labels;
		parent::__construct( $_name, $_desc, $_id, '', $_validation_cb );
		$this->options_checked = is_array( $_options_checked ) ? $_options_checked : $this->get();
	}

	function write_html() {
		?>
		<tr valign="top">
			<th scope="row"><label for="<?php echo $this->id; ?>"><?php echo esc_html( $this->name ); ?></label></th>
			<td>
				<input type="hidden" name="<?php echo esc_attr( $this->id ); ?>" />
				<?php $count = 0; ?>
				<?php foreach( $this->options as $option ) : ?>
				<?php $checked =  in_array( $option , (array) $this->options_checked ) ? ' checked="checked" ' : ''; ?>
					<?php $option_label = isset( $this->options_labels[$count] ) ? $this->options_labels[$count] : $option; ?>
					<input type="checkbox" name="<?php echo esc_attr( $this->id ); ?>[]" id="<?php echo esc_attr( $this->id ); ?>-<?php echo esc_attr( $option ); ?>" value="<?php echo esc_attr( $option ); ?>" <?php echo $checked; ?> />
					<label for="<?php echo esc_attr( $this->id ); ?>-<?php echo esc_attr( $option ); ?>"><?php echo esc_html( $option_label ); ?></label>
					<?php $count++; ?>
				<br />
				<?php endforeach; ?>

			</td>
		</tr>
		<tr valign="top">
			<td colspan=2>
				<label for="<?php echo $this->id; ?>"><small><?php echo esc_html( $this->desc ); ?></small></label><hr />
			</td>
		</tr>
		<?php
	}
}

class CheezCapImportData {
	var $dict = array();
}
