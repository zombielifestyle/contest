<?php

/*
	TODO
	 - auto verify mocks
 	 
**/

require __DIR__ . '/../lib/contest.php';

class Storage {
	function set($k, $v) {
		$this->$k = $v;
		return true;
	}
	function get($k) {
		return $this->$k;
	}
}

class Stack {
	protected $stack = array();
	function push($v) {
		$this->stack[] = $v;
		return count($this->stack);
	}
	function pop() {
		return array_pop($this->stack);
	}
}

class test_ConTest extends PHPUnit_Framework_TestCase {

	protected $mocks = array();
	
	function intercept($object, $alias = null) {
		$mock = new contest_Mock();
		if ($alias) {
			$this->mocks[$alias] = $mock;
		}
		return new contest_ObjectInterceptor($object, $mock);
	}

	function mock($alias) {
		return clone $this->mocks[$alias];
	}

	function testStorageValid() {
		$storage = $this->intercept(new Storage, 'Storage');

		$storage->set('k', 'v')
			->is(equalTo(true));
		$storage->get('k')
			->is(equalTo('v'));

		$storageMock = $this->mock('Storage');
		$returnValue = $storageMock->set('k','v');
		$this->assertTrue($returnValue);
		$returnValue = $storageMock->get('k');
		$this->assertEquals('v', $returnValue);

		$storageMock->_verify();
	}

	/**
	 * @expectedException contest_AssertionFailed
	 * @expectedExceptionMessage expected that (string) "v" is not equal to (string) "v"
	 */
	function testStorageFails() {
		$storage = $this->intercept(new Storage);

		$storage->set('k', 'v')
			->is(equalTo(true));
		$storage->get('k')
			->is(not(equalTo('v')));
	}

	function testStackValid() {
		$stack = $this->intercept(new Stack, 'Stack');

		$stack->push('A')
			->is(identicalTo(1));
		$stack->push('B')
			->is(identicalTo(2));
		$stack->pop()
			->is(equalTo('B'));
		$stack->pop()
			->is(equalTo('A'));
		$stack->pop()
			->is(equalTo(null));

		$stackMock = $this->mock('Stack');
		$returnValue = $stackMock->push('A');
		$this->assertEquals(1, $returnValue);
		$returnValue = $stackMock->push('B');
		$this->assertEquals(2, $returnValue);
		$returnValue = $stackMock->pop();
		$this->assertEquals('B', $returnValue);
		$returnValue = $stackMock->pop();
		$this->assertEquals('A', $returnValue);
		$returnValue = $stackMock->pop();
		$this->assertEquals(null, $returnValue);

		$stackMock->_verify();

	}

	function testValue() {
		$value = new contest_matcher_Value(12);
		$this->assertEquals('(integer) 12', (string)$value);
		$this->assertSame(12, $value->evaluate());

		$value = new contest_matcher_Value("12");
		$this->assertEquals('(string) "12"', (string)$value);
		$this->assertSame('12', $value->evaluate());

		$value = new contest_matcher_Value(true);
		$this->assertEquals('(boolean) TRUE', (string)$value);
		$this->assertSame(true, $value->evaluate());

		$value = new contest_matcher_Value(12.12);
		$this->assertEquals('(float) 12.12', (string)$value);
		$this->assertSame(12.12, $value->evaluate());

		$obj = new stdClass;
		$value = new contest_matcher_Value($obj);
		$this->assertEquals('(object) stdClass', (string)$value);
		$this->assertSame($obj, $value->evaluate());

		$obj = array(1,2);
		$value = new contest_matcher_Value($obj);
		$this->assertEquals('(array) 2', (string)$value);
		$this->assertSame($obj, $value->evaluate());

	}

	function testEqual() {
		$matcher = new contest_matcher_Equal(new contest_matcher_Value(true));
		$this->assertEquals("equal to (boolean) TRUE", (string)$matcher);
		$this->assertTrue($matcher->match(true));
		$this->assertFalse($matcher->match(false));
	}

	function testIdentical() {
		$obj = new stdClass;
		$matcher = new contest_matcher_Identical(new contest_matcher_Value($obj));
		$this->assertEquals("identical to (object) stdClass", (string)$matcher);
		$this->assertTrue($matcher->match($obj));
		$this->assertFalse($matcher->match(new stdClass));
	}

	function testNotEqual() {
		$matcher = new contest_matcher_Not(new contest_matcher_Equal(new contest_matcher_Value(true)));
		$this->assertEquals("not equal to (boolean) TRUE", (string)$matcher);
		$this->assertTrue($matcher->match(false));
		$this->assertFalse($matcher->match(true));
	}

	function testAllOf() {
		$matcher = new contest_matcher_AllOf(
			new contest_matcher_Equal(new contest_matcher_Value(true)),
			new contest_matcher_Equal(new contest_matcher_Value(1))
		);
		$this->assertEquals("allOf(equal to (boolean) TRUE, equal to (integer) 1)", (string)$matcher);
		$this->assertTrue($matcher->match(true));
		$this->assertTrue($matcher->match(1));
		$this->assertFalse($matcher->match(false));
	}

	function testAnyOf() {
		$matcher = new contest_matcher_AnyOf(
			new contest_matcher_Identical(new contest_matcher_Value(true)),
			new contest_matcher_Identical(new contest_matcher_Value(1))
		);
		$this->assertEquals("anyOf(identical to (boolean) TRUE, identical to (integer) 1)", (string)$matcher);
		$this->assertTrue($matcher->match(true));
		$this->assertTrue($matcher->match(1));
		$this->assertFalse($matcher->match(false));
	}

	function testInstanceOf() {
		$matcher = new contest_matcher_InstanceOfClass(new contest_matcher_Value('stdClass', 'class'));
		$this->assertEquals("instance of (class) stdClass", (string)$matcher);
		$this->assertTrue($matcher->match(new stdClass));
		$this->assertFalse($matcher->match(new SplFileObject(__FILE__)));
	}

	function testLessThan() {
		$matcher = new contest_matcher_LessThan(new contest_matcher_Value(12));
		$this->assertEquals("less than (integer) 12", (string)$matcher);
		$this->assertTrue($matcher->match(10));
		$this->assertFalse($matcher->match(14));
	}

	function testGreaterThan() {
		$matcher = new contest_matcher_GreaterThan(new contest_matcher_Value(12));
		$this->assertEquals("greater than (integer) 12", (string)$matcher);
		$this->assertTrue($matcher->match(14));
		$this->assertFalse($matcher->match(10));
	}

	function testHasKey() {
		$matcher = new contest_matcher_HasKey(new contest_matcher_Value('le-key'));
		$this->assertEquals("has key (string) \"le-key\"", (string)$matcher);
		$this->assertTrue($matcher->match(array('le-key' => null)));
		$this->assertFalse($matcher->match(array()));
	}

	function testHasValue() {
		$matcher = new contest_matcher_HasValue(new contest_matcher_Value('le-value'));
		$this->assertEquals("has value (string) \"le-value\"", (string)$matcher);
		$this->assertTrue($matcher->match(array('le-value')));
		$this->assertFalse($matcher->match(array()));
	}

	function testShortcuts() {
		$matcher = equalTo(true);
		$this->assertInstanceOf('contest_matcher_Equal', $matcher);
		$matcher = identicalTo(true);
		$this->assertInstanceOf('contest_matcher_Identical', $matcher);
		$matcher = not(true);
		$this->assertInstanceOf('contest_matcher_Not', $matcher);
		$matcher = anyOf(true);
		$this->assertInstanceOf('contest_matcher_AnyOf', $matcher);
		$matcher = allOf(true);
		$this->assertInstanceOf('contest_matcher_AllOf', $matcher);
		$matcher = instanceOfClass(true);
		$this->assertInstanceOf('contest_matcher_InstanceOfClass', $matcher);
		$matcher = greaterThan(true);
		$this->assertInstanceOf('contest_matcher_GreaterThan', $matcher);
		$matcher = lessThan(true);
		$this->assertInstanceOf('contest_matcher_LessThan', $matcher);
		$matcher = hasKey(true);
		$this->assertInstanceOf('contest_matcher_HasKey', $matcher);
		$matcher = hasValue(true);
		$this->assertInstanceOf('contest_matcher_HasValue', $matcher);
	}

}
