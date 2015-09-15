<div class="form-horizontal">
    <?php foreach ($fields as $el): ?>
        <div class="control-group">
            <label class="control-label" for="<?php echo $el; ?>">
                <?php print_string($el, 'auth_oauth'); ?>
            </label>
            <div class="controls">
                <input type="text" name="<?php echo $el; ?>" value="<?php echo property_exists($values, $el) ? $values->{$el} : ''; ?>"/>
            </div>
            <div class="errors">
                <?php echo array_key_exists($el, $err) ? $err[$el] : ''; ?>
            </div>
        </div>
    <?php endforeach; ?>
    <div class="control-group">
        <label class="control-label" for="oauth_redirect">
            <?php print_string('oauth_redirect', 'auth_oauth'); ?>
        </label>
        <div class="controls">
            <input type="checkbox" name="oauth_redirect" <?php echo $redirect ? 'checked' : ''; ?>/>
        </div>
    </div>
</div>
<div id="datamapping">
    <table class="center">
        <?php print_auth_lock_options('oauth', $user_fields, '<!-- empty help -->', true, false); ?>
    </table>
</div>
