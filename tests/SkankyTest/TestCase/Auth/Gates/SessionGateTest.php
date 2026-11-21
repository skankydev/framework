<?php

namespace SkankyTest\TestCase\Auth\Gates;

use PHPUnit\Framework\TestCase;
use SkankyDev\Auth\Event\LoggedIn;
use SkankyDev\Auth\Event\LoggedOut;
use SkankyDev\Auth\Event\LoginFailed;
use SkankyDev\Auth\Gates\SessionGate;
use SkankyDev\Event\EventManager;
use SkankyDev\Utilities\Hash;
use SkankyDev\Utilities\Session;
use SkankyDev\Utilities\Token;

/**
 * Provider factice : duck-type de MasterCollection (findOne/findById/save)
 * pour tester SessionGate sans MongoDB.
 */
class FakeAuthProvider {
    /** @var object[] Un seul jeu de données, indexé par _id — comme une vraie collection Mongo. */
    public array $users = [];

    public function addUser(object $user): void {
        $this->users[(string) $user->_id] = $user;
    }

    public function findOne(array $filter): ?object {
        foreach ($this->users as $user) {
            foreach ($filter as $key => $value) {
                if (!isset($user->$key) || $user->$key !== $value) {
                    continue 2;
                }
            }
            return $user;
        }
        return null;
    }

    public function findById(string $id): ?object {
        return $this->users[$id] ?? null;
    }

    public function save(object $doc): bool {
        $this->users[(string) $doc->_id] = $doc;
        return true;
    }

    public function saveSneaky(object $doc): bool {
        return $this->save($doc);
    }
}

class TestableSessionGate extends SessionGate {
    public function __construct(array $config, public FakeAuthProvider $fakeProvider) {
        parent::__construct($config, 'FixtureUserDocument');
    }

    protected function provider(): object {
        return $this->fakeProvider;
    }
}

class SessionGateTest extends TestCase
{
    protected function setUp(): void {
        $_SESSION = [];
        $_COOKIE = [];
    }

    private function makeUser(string $id, string $email, string $password): object {
        $user = new \stdClass();
        $user->_id = $id;
        $user->email = $email;
        $user->password = Hash::make($password);
        $user->remember_token = null;
        return $user;
    }

    // ── attempt ───────────────────────────────────────────────────────────────

    public function testAttemptSucceedsWithValidCredentials(): void {
        $provider = new FakeAuthProvider();
        $provider->addUser($this->makeUser('u1', 'simon@skankydev.com', 'secret'));

        $gate = new TestableSessionGate(['identifier' => 'email'], $provider);

        $this->assertTrue($gate->attempt(['email' => 'simon@skankydev.com', 'password' => 'secret']));
        $this->assertSame('u1', $gate->id());
    }

    public function testAttemptFailsWithWrongPassword(): void {
        $provider = new FakeAuthProvider();
        $provider->addUser($this->makeUser('u1', 'simon@skankydev.com', 'secret'));

        $gate = new TestableSessionGate(['identifier' => 'email'], $provider);

        $this->assertFalse($gate->attempt(['email' => 'simon@skankydev.com', 'password' => 'wrong']));
        $this->assertNull($gate->id());
    }

    public function testAttemptFailsWithUnknownEmail(): void {
        $gate = new TestableSessionGate(['identifier' => 'email'], new FakeAuthProvider());
        $this->assertFalse($gate->attempt(['email' => 'nobody@skankydev.com', 'password' => 'secret']));
    }

    public function testAttemptFailsWithMissingCredentials(): void {
        $gate = new TestableSessionGate(['identifier' => 'email'], new FakeAuthProvider());
        $this->assertFalse($gate->attempt(['email' => 'simon@skankydev.com']));
        $this->assertFalse($gate->attempt(['password' => 'secret']));
    }

    // ── resolve (session) ─────────────────────────────────────────────────────

    public function testUserIsResolvedFromSession(): void {
        $provider = new FakeAuthProvider();
        $user = $this->makeUser('u1', 'simon@skankydev.com', 'secret');
        $provider->addUser($user);
        Session::set('auth.user_id', 'u1');

        $gate = new TestableSessionGate(['identifier' => 'email'], $provider);
        $this->assertSame($user, $gate->user());
    }

    public function testUserIsNullWithoutSessionOrCookie(): void {
        $gate = new TestableSessionGate(['identifier' => 'email'], new FakeAuthProvider());
        $this->assertNull($gate->user());
    }

    // ── remember-me ───────────────────────────────────────────────────────────

    public function testAttemptWithRememberStoresHashedTokenOnUser(): void {
        $provider = new FakeAuthProvider();
        $user = $this->makeUser('u1', 'simon@skankydev.com', 'secret');
        $provider->addUser($user);

        $gate = new TestableSessionGate(['identifier' => 'email', 'remember' => true], $provider);
        $gate->attempt(['email' => 'simon@skankydev.com', 'password' => 'secret', 'remember' => true]);

        $this->assertInstanceOf(Token::class, $user->remember_token);
    }

    public function testAttemptWithoutRememberFlagDoesNotSetToken(): void {
        $provider = new FakeAuthProvider();
        $user = $this->makeUser('u1', 'simon@skankydev.com', 'secret');
        $provider->addUser($user);

        $gate = new TestableSessionGate(['identifier' => 'email', 'remember' => true], $provider);
        $gate->attempt(['email' => 'simon@skankydev.com', 'password' => 'secret']); // pas de "remember"

        $this->assertNull($user->remember_token);
    }

    public function testResolveFromRememberCookieRestoresSession(): void {
        $provider = new FakeAuthProvider();
        $user = $this->makeUser('u1', 'simon@skankydev.com', 'secret');
        $plain = new Token();
        $user->remember_token = new Token(['value' => Hash::hashToken($plain->value), 'time' => $plain->time]);
        $provider->addUser($user);

        $_COOKIE['auth_remember_token'] = 'u1|' . $plain->value;

        $gate = new TestableSessionGate(['identifier' => 'email', 'remember' => true], $provider);
        $this->assertSame($user, $gate->user());
        $this->assertSame('u1', Session::get('auth.user_id'));
    }

    public function testResolveFromRememberCookieFailsWithWrongToken(): void {
        $provider = new FakeAuthProvider();
        $user = $this->makeUser('u1', 'simon@skankydev.com', 'secret');
        $plain = new Token();
        $user->remember_token = new Token(['value' => Hash::hashToken($plain->value), 'time' => $plain->time]);
        $provider->addUser($user);

        $_COOKIE['auth_remember_token'] = 'u1|not-the-right-token';

        $gate = new TestableSessionGate(['identifier' => 'email', 'remember' => true], $provider);
        $this->assertNull($gate->user());
    }

    public function testResolveIgnoresRememberCookieWhenGuardOptionDisabled(): void {
        $provider = new FakeAuthProvider();
        $user = $this->makeUser('u1', 'simon@skankydev.com', 'secret');
        $plain = new Token();
        $user->remember_token = new Token(['value' => Hash::hashToken($plain->value), 'time' => $plain->time]);
        $provider->addUser($user);

        $_COOKIE['auth_remember_token'] = 'u1|' . $plain->value;

        // 'remember' absent de la config -> le cookie n'est jamais consulté
        $gate = new TestableSessionGate(['identifier' => 'email'], $provider);
        $this->assertNull($gate->user());
    }

    // ── logout ────────────────────────────────────────────────────────────────

    public function testLogoutClearsSessionAndRememberToken(): void {
        $provider = new FakeAuthProvider();
        $user = $this->makeUser('u1', 'simon@skankydev.com', 'secret');
        $user->remember_token = new Token();
        $provider->addUser($user);
        Session::set('auth.user_id', 'u1');

        $gate = new TestableSessionGate(['identifier' => 'email'], $provider);
        $gate->logout();

        $this->assertNull($user->remember_token);
        $this->assertNull(Session::get('auth.user_id'));
    }

    // ── events ────────────────────────────────────────────────────────────────

    /** Collects every auth event emitted from now on. */
    private function recordEvents(): \ArrayObject {
        (new \ReflectionProperty(EventManager::class, '_instance'))->setValue(null, null);
        $events = new \ArrayObject();
        foreach ([LoggedIn::class, LoggedOut::class, LoginFailed::class] as $class) {
            EventManager::_listen($class, fn($e) => $events->append($e));
        }
        return $events;
    }

    protected function tearDown(): void {
        (new \ReflectionProperty(EventManager::class, '_instance'))->setValue(null, null);
    }

    public function testAttemptEmitsLoggedIn(): void {
        $provider = new FakeAuthProvider();
        $user = $this->makeUser('u1', 'simon@skankydev.com', 'secret');
        $provider->addUser($user);
        $events = $this->recordEvents();

        (new TestableSessionGate(['identifier' => 'email'], $provider))
            ->attempt(['email' => 'simon@skankydev.com', 'password' => 'secret', 'remember' => true]);

        $this->assertCount(1, $events);
        $this->assertInstanceOf(LoggedIn::class, $events[0]);
        $this->assertSame($user, $events[0]->user);
        $this->assertFalse($events[0]->remember);   // remember absent de la config
        $this->assertFalse($events[0]->viaCookie);
    }

    public function testWrongPasswordEmitsLoginFailedWithoutPassword(): void {
        $provider = new FakeAuthProvider();
        $provider->addUser($this->makeUser('u1', 'simon@skankydev.com', 'secret'));
        $events = $this->recordEvents();

        (new TestableSessionGate(['identifier' => 'email'], $provider))
            ->attempt(['email' => 'simon@skankydev.com', 'password' => 'wrong']);

        $this->assertCount(1, $events);
        $this->assertInstanceOf(LoginFailed::class, $events[0]);
        $this->assertEquals('email', $events[0]->identifier);
        $this->assertEquals('simon@skankydev.com', $events[0]->login);
        $this->assertStringNotContainsString('wrong', serialize($events[0]));
    }

    public function testMissingLoginEmitsLoginFailedWithNullLogin(): void {
        $events = $this->recordEvents();
        (new TestableSessionGate(['identifier' => 'email'], new FakeAuthProvider()))->attempt(['password' => 'x']);
        $this->assertNull($events[0]->login);
    }

    public function testRememberCookieEmitsLoggedInOnceAfterResolution(): void {
        $provider = new FakeAuthProvider();
        $user = $this->makeUser('u1', 'simon@skankydev.com', 'secret');
        $plain = new Token();
        $user->remember_token = new Token(['value' => Hash::hashToken($plain->value), 'time' => $plain->time]);
        $provider->addUser($user);
        $_COOKIE['auth_remember_token'] = 'u1|' . $plain->value;
        // Request garde les cookies du premier test qui l'a instancié
        (new \ReflectionProperty(\SkankyDev\Http\Request::class, '_instance'))->setValue(null, null);

        $events = $this->recordEvents();
        $gate = new TestableSessionGate(['identifier' => 'email', 'remember' => true], $provider);
        // un listener qui relit l'utilisateur ne relance pas la résolution
        $seen = null;
        EventManager::_listen(LoggedIn::class, function () use ($gate, &$seen) { $seen = $gate->user(); });

        $gate->user();
        $gate->user();

        $this->assertCount(1, $events);
        $this->assertTrue($events[0]->viaCookie);
        $this->assertTrue($events[0]->remember);
        $this->assertSame($user, $seen);
    }

    public function testLogoutEmitsLoggedOutOnlyWhenLoggedIn(): void {
        $provider = new FakeAuthProvider();
        $user = $this->makeUser('u1', 'simon@skankydev.com', 'secret');
        $provider->addUser($user);
        $events = $this->recordEvents();

        (new TestableSessionGate(['identifier' => 'email'], $provider))->logout();
        $this->assertCount(0, $events);

        Session::set('auth.user_id', 'u1');
        (new TestableSessionGate(['identifier' => 'email'], $provider))->logout();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(LoggedOut::class, $events[0]);
        $this->assertSame($user, $events[0]->user);
    }
}
