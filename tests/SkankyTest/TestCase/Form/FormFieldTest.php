<?php

namespace SkankyTest\TestCase\Form;

use PHPUnit\Framework\TestCase;
use SkankyDev\Form\Fields\TextField;
use SkankyDev\Form\Fields\SelectField;
use SkankyDev\Form\Fields\RadioField;

class FormFieldTest extends TestCase
{
    // ── TextField ─────────────────────────────────────────────────────────────

    public function testGetName(): void {
        $field = new TextField('username');
        $this->assertEquals('username', $field->getName());
    }

    public function testDefaultValueIsNull(): void {
        $field = new TextField('username');
        $this->assertNull($field->getValue());
    }

    public function testSetAndGetValue(): void {
        $field = new TextField('username');
        $field->setValue('Simon');
        $this->assertEquals('Simon', $field->getValue());
    }

    public function testValueFromOptions(): void {
        $field = new TextField('username', ['value' => 'Simon']);
        $this->assertEquals('Simon', $field->getValue());
    }

    public function testDefaultFallback(): void {
        $field = new TextField('username', ['default' => 'anonymous']);
        $this->assertEquals('anonymous', $field->getValue());
    }

    public function testIsRequiredFalseByDefault(): void {
        $field = new TextField('username');
        $this->assertFalse($field->isRequired());
        $this->assertEquals('', $field->required());
    }

    public function testIsRequiredTrueWhenRuleSet(): void {
        $field = new TextField('username', ['rules' => ['required']]);
        $this->assertTrue($field->isRequired());
        $this->assertEquals('required', $field->required());
    }

    public function testGetRules(): void {
        $field = new TextField('age', ['rules' => ['required', 'numeric']]);
        $this->assertEquals(['required', 'numeric'], $field->getRules());
    }

    public function testNoErrorsByDefault(): void {
        $field = new TextField('username');
        $this->assertFalse($field->hasErrors());
        $this->assertEmpty($field->getErrors());
        $this->assertNull($field->getFirstError());
    }

    public function testSetErrors(): void {
        $field = new TextField('username');
        $field->setErrors(['Le champ est requis', 'Trop court']);
        $this->assertTrue($field->hasErrors());
        $this->assertCount(2, $field->getErrors());
        $this->assertEquals('Le champ est requis', $field->getFirstError());
    }

    public function testErrorsFromOptions(): void {
        $field = new TextField('username', ['errors' => ['Erreur quelconque']]);
        $this->assertTrue($field->hasErrors());
    }

    public function testSetValueIsChainable(): void {
        $field = new TextField('username');
        $result = $field->setValue('Simon');
        $this->assertSame($field, $result);
    }

    // ── SelectField ───────────────────────────────────────────────────────────

    public function testSelectFieldOptions(): void {
        $field = new SelectField('type', [
            'options' => ['scenario' => 'Scenario', 'screen' => 'Screen'],
        ]);
        $ref = new \ReflectionProperty($field, 'options');
        $this->assertEquals(['scenario' => 'Scenario', 'screen' => 'Screen'], $ref->getValue($field));
    }

    public function testSelectFieldEmptyDefaultsFalse(): void {
        $field = new SelectField('type', ['options' => []]);
        $ref = new \ReflectionProperty($field, 'empty');
        $this->assertFalse($ref->getValue($field));
    }

    public function testSelectFieldEmptyFlag(): void {
        $field = new SelectField('type', ['options' => [], 'empty' => true]);
        $ref = new \ReflectionProperty($field, 'empty');
        $this->assertTrue($ref->getValue($field));
    }

    // ── RadioField ────────────────────────────────────────────────────────────

    public function testRadioFieldStoresOptions(): void {
        $field = new RadioField('color', [
            'options' => ['red' => 'Rouge', 'blue' => 'Bleu'],
        ]);
        $ref = new \ReflectionProperty($field, 'options');
        $this->assertEquals(['red' => 'Rouge', 'blue' => 'Bleu'], $ref->getValue($field));
    }

    public function testRadioFieldEmptyOptionsWhenNoneProvided(): void {
        $field = new RadioField('color');
        $ref = new \ReflectionProperty($field, 'options');
        $this->assertEmpty($ref->getValue($field));
    }

    public function testRadioFieldInheritsFormFieldBehaviour(): void {
        $field = new RadioField('color', ['rules' => ['required']]);
        $this->assertEquals('color', $field->getName());
        $this->assertTrue($field->isRequired());
    }

    public function testRadioFieldTypeIsRadio(): void {
        $field = new RadioField('color');
        $ref = new \ReflectionProperty($field, 'type');
        $this->assertEquals('radio', $ref->getValue($field));
    }

    // ── render / makePath ────────────────────────────────────────────────────��

    public function testRenderProducesHtml(): void {
        $field = new TextField('username', ['label' => 'Nom', 'value' => 'Simon']);
        $html  = $field->render();

        $this->assertStringContainsString('<input', $html);
        $this->assertStringContainsString('name="username"', $html);
        $this->assertStringContainsString('Simon', $html);
    }

    public function testRenderWithErrors(): void {
        $field = new TextField('username', ['errors' => ['Champ requis']]);
        $html  = $field->render();

        $this->assertStringContainsString('Champ requis', $html);
        $this->assertStringContainsString('has-error', $html);
    }

    public function testRenderRequiredAddsClass(): void {
        $field = new TextField('username', ['rules' => ['required'], 'label' => 'Nom']);
        $html  = $field->render();

        $this->assertStringContainsString('required', $html);
    }

    public function testMakePathThrowsForMissingTemplate(): void {
        $field = new TextField('test', ['viewHtml' => 'fields.nonexistent']);
        // Override the protected viewHtml property via reflection
        $ref = new \ReflectionProperty($field, 'viewHtml');
        $ref->setValue($field, 'fields.nonexistent');

        $this->expectException(\Exception::class);
        $this->expectExceptionCode(601);
        $field->render();
    }
}
