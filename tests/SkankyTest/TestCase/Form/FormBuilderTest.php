<?php

namespace SkankyTest\TestCase\Form;

use PHPUnit\Framework\TestCase;
use SkankyDev\Form\FormBuilder;
use SkankyDev\Http\Routing\Router;
use SkankyDev\Http\UrlBuilder;

// Minimal concrete form for testing
class TestForm extends FormBuilder {
    public function build(): void {
        $this->add('name',  'text',   ['label' => 'Nom',   'rules' => ['required']]);
        $this->add('email', 'text',   ['label' => 'Email',  'rules' => ['email']]);
        $this->add('type',  'select', [
            'label'   => 'Type',
            'options' => ['a' => 'A', 'b' => 'B'],
        ]);
    }
}

class FormBuilderTest extends TestCase
{
    protected function setUp(): void {
        // Reset Singletons so each test starts clean
        $ref = new \ReflectionProperty(Router::class, '_instance');
        $ref->setValue(null, null);
        $ref = new \ReflectionProperty(UrlBuilder::class, '_instance');
        $ref->setValue(null, null);

        // Session superglobal must exist for Session::getAndClean()
        $_SESSION = [];

        Router::_findCurrentRoute('/test/index');
    }

    private function makeForm(): TestForm {
        return new TestForm();
    }

    public function testBuildPopulatesFields(): void {
        $form = $this->makeForm();
        $form->build();
        $fields = $form->getFields();
        $this->assertArrayHasKey('name',  $fields);
        $this->assertArrayHasKey('email', $fields);
        $this->assertArrayHasKey('type',  $fields);
    }

    public function testSetDataFillsValues(): void {
        $form = $this->makeForm();
        $form->setData(['name' => 'Simon', 'email' => 'simon@skankydev.com']);
        $this->assertEquals(['name' => 'Simon', 'email' => 'simon@skankydev.com'], $form->getData());
    }

    public function testSetDataAcceptsObject(): void {
        $obj = new \stdClass();
        $obj->name = 'Simon';
        $form = $this->makeForm();
        $form->setData($obj);
        $this->assertEquals(['name' => 'Simon'], $form->getData());
    }

    public function testValidateReturnsTrueOnValidData(): void {
        $form = $this->makeForm();
        $result = $form->validate(['name' => 'Simon', 'email' => 'simon@skankydev.com', 'type' => 'a']);
        $this->assertTrue($result);
        $this->assertEmpty($form->getErrors());
    }

    public function testValidateReturnsFalseOnInvalidData(): void {
        $form = $this->makeForm();
        $result = $form->validate(['name' => '', 'email' => 'not-an-email', 'type' => 'a']);
        $this->assertFalse($result);
        $this->assertNotEmpty($form->getErrors());
        $this->assertArrayHasKey('name', $form->getErrors());
    }

    public function testSetErrors(): void {
        $form = $this->makeForm();
        $form->build();
        $form->setErrors(['name' => ['Le champ est requis']]);
        $this->assertArrayHasKey('name', $form->getErrors());
        $this->assertTrue($form->getFields()['name']->hasErrors());
    }

    public function testSubmitIsChainable(): void {
        $form = $this->makeForm();
        $result = $form->submit('Enregistrer');
        $this->assertSame($form, $result);
    }

    public function testAddThrowsOnUnknownFieldType(): void {
        $this->expectException(\Exception::class);
        $form = $this->makeForm();
        $form->add('foo', 'nonexistent_type');
    }

    public function testRenderFieldThrowsWhenFieldMissing(): void {
        $this->expectException(\Exception::class);
        $form = $this->makeForm();
        $form->build();
        $form->renderField('does_not_exist');
    }

    // ── render / open / close ─────────────────────────────────────────────────

    public function testOpenReturnsFormOpenTag(): void {
        $form = $this->makeForm();
        $form->build();
        $html = $form->open();

        $this->assertStringContainsString('<form', $html);
        $this->assertStringContainsString('method="POST"', $html);
    }

    public function testOpenCallsBuildWhenFieldsEmpty(): void {
        $form = $this->makeForm();
        // Fields are empty — open() should auto-call build()
        $html = $form->open();
        $this->assertNotEmpty($form->getFields());
        $this->assertStringContainsString('<form', $html);
    }

    public function testOpenIncludesCsrfForPost(): void {
        $form = $this->makeForm();
        $form->build();
        $html = $form->open();

        $this->assertStringContainsString('_token', $html);
    }

    public function testCloseReturnsClosingTag(): void {
        $form = $this->makeForm();
        $this->assertEquals('</form>', $form->close());
    }

    public function testRenderProducesFullForm(): void {
        $form = $this->makeForm();
        $form->build();
        $html = $form->render();

        $this->assertStringContainsString('<form', $html);
        $this->assertStringContainsString('</form>', $html);
        $this->assertStringContainsString('<input', $html);
        $this->assertStringContainsString('name="name"', $html);
        $this->assertStringContainsString('<button', $html);
    }

    public function testAddWithOldInputUsesFlashedValue(): void {
        // Simuler un ancien input flashé en session
        $_SESSION['old'] = ['name' => 'Simon flashé'];

        $form = new TestForm();
        $form->build();

        $this->assertEquals('Simon flashé', $form->getFields()['name']->getValue());
    }

    public function testConstructorAcceptsRawUrlString(): void {
        // Pour les routes déclarées explicitement (ex: Auth\Login), construire
        // le link array attendu par UrlBuilder est superflu : on passe l'URL telle quelle.
        $form = new TestForm('/login');
        $form->build();
        $html = $form->open();

        $this->assertStringContainsString('action="/login"', $html);
    }

    // ── validate() ne touche plus $this->data ───────────────────────────────────

    public function testValidateDoesNotOverwriteExplicitlySetData(): void {
        // setData(user) posé par le controller avant validate() doit survivre —
        // c'est ce qui permet à build() de lire $this->data['_id'] de façon fiable
        // (ex: rule unique:...,{id} qui doit s'exclure elle-même en édition).
        $form = $this->makeForm();
        $form->setData(['name' => 'Simon', 'email' => 'simon@skankydev.com', '_id' => 'abc123']);

        $form->validate(['name' => 'Autre nom', 'email' => 'autre@skankydev.com', 'type' => 'a']);

        $this->assertSame('abc123', $form->getData()['_id']);
        $this->assertSame('Simon', $form->getData()['name']);
    }

    public function testValidateStillValidatesTheGivenDataNotThePriorData(): void {
        // Même si $this->data n'est plus écrasé, c'est bien l'argument $data
        // (l'input soumis) qui doit être validé, pas les anciennes données.
        $form = $this->makeForm();
        $form->setData(['name' => 'Simon', 'email' => 'simon@skankydev.com']);

        $result = $form->validate(['name' => '', 'email' => 'not-an-email', 'type' => 'a']);

        $this->assertFalse($result);
        $this->assertArrayHasKey('name', $form->getErrors());
    }
}
