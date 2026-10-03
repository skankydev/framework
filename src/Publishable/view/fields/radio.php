<div class="form-group <?= $errors ? 'has-error' : '' ?>">
    <?php if ($label): ?>
        <label <?= $this->createAttr($labelAttr) ?>><?= $label ?></label>
    <?php endif; ?>
    
    <div class="radio-group">
        <?php foreach ($options as $optionValue => $optionLabel): ?>
            <div class="radio-item">
                <input 
                    type="radio" 
                    id="<?= $id ?>_<?= $optionValue ?>" 
                    name="<?= $name ?>" 
                    value="<?= e($optionValue) ?>"
                    <?= $value == $optionValue ? 'checked' : '' ?>
                    <?= $this->createAttr($attributes) ?>
                >
                <label for="<?= $id ?>_<?= $optionValue ?>">
                    <?= e($optionLabel) ?>
                </label>
            </div>
        <?php endforeach; ?>
    </div>
    
    <?php if ($errors): ?>
        <span class="error"><?= e($errors[0]) ?></span>
    <?php endif; ?>
</div>