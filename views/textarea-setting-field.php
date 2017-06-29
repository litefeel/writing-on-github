<?php
/**
 * Test Option Field.
 * @package Writing_On_GitHub
 */

?>
<?php $value = get_option( $args['name'], $args['default'] ); ?>
<textarea name="<?php echo esc_attr( $args['name'] ); ?>" id="<?php echo esc_attr( $args['name'] ); ?>" rows="10" cols="40">
<?php echo esc_attr( $value ); ?>
</textarea>
<p class="description"><?php echo $args['help_text']; ?></p>
