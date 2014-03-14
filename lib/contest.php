<?php

class contest_matcher_Value {

	protected $value;
	protected $type;

	function __construct($value, $type = null) {
		$this->value = $value;
		$this->type = $type ? $type : gettype($value);
	}

	function evaluate() {
		return $this->value;
	}

	function match(){
		throw new Exception();
	}

	function __toString() {
		$str = null;
		switch ($this->type) {
			case 'NULL':
				$str = $this->type;
			break;
			case 'integer':
				$str = '(integer) ' . $this->value;
			break;
			case 'double':
				$str = '(float) ' . $this->value;
			break;
			case 'class':
				$str = '(class) ' . $this->value;
			break;
			case 'boolean':
				$str = '(boolean) ' . ($this->value ? 'TRUE' : 'FALSE');
			break;
			case 'string':
				$str = '(string) "' . $this->value . '"';
			break;
			case 'object':
				$str = '(object) ' . get_class($this->value);
			break;
			case 'array':
				$str = '(array) ' . count($this->value);
			break;
			default:
				throw new Exception('TODO');
		}
		return (string)$str;
	}

}

abstract class contest_matcher_Matcher {

	protected $matcher;

	function __construct($matcher) {
		$this->matcher = $matcher;
	}

	function evaluate() {
		return $this->matcher->evaluate();
	}

	abstract function match($value);

}

class contest_matcher_Equal extends contest_matcher_Matcher {

	function match($value) {
		return $value == $this->matcher->evaluate();
	}

	function __toString() {
		return 'equal to ' . (string)$this->matcher;
	}

}

class contest_matcher_Identical extends contest_matcher_Matcher {

	function match($value) {
		return $value === $this->matcher->evaluate();
	}

	function __toString() {
		return 'identical to ' . (string)$this->matcher;
	}

}

class contest_matcher_Not extends contest_matcher_Matcher {

	function match($value) {
		return !$this->matcher->match($value);
	}

	function __toString() {
		return 'not ' . (string)$this->matcher;
	}

}

class contest_matcher_AllOf extends contest_matcher_Matcher {

	function __construct($matcher) {
		$this->matcher = func_get_args();
	}

	function match($value) {
		foreach ($this->matcher as $matcher) {
			if (!$matcher->match($value)) {
				return false;
			}
		}
		return true;
	}

	function __toString() {
		$str = '';
		foreach ($this->matcher as $matcher) {
			$str .= (string)$matcher . ', ';
		}
		return 'allOf(' . rtrim($str, ', ') . ')';
	}

}

class contest_matcher_AnyOf extends contest_matcher_Matcher {

	function __construct($matcher) {
		$this->matcher = func_get_args();
	}

	function match($value) {
		foreach ($this->matcher as $matcher) {
			if ($matcher->match($value)) {
				return true;
			}
		}
		return false;
	}

	function __toString() {
		$str = '';
		foreach ($this->matcher as $matcher) {
			$str .= (string)$matcher . ', ';
		}
		return 'anyOf(' . rtrim($str, ', ') . ')';
	}

}

class contest_matcher_InstanceOfClass extends contest_matcher_Matcher {

	function match($value) {
		return is_object($value) && get_class($value) == $this->matcher->evaluate();
	}

	function __toString() {
		return 'instance of ' . (string)$this->matcher;
	}

}

class contest_matcher_LessThan extends contest_matcher_Matcher {

	function match($value) {
		return $value < $this->matcher->evaluate();
	}

	function __toString() {
		return 'less than ' . (string)$this->matcher;
	}

}

class contest_matcher_GreaterThan extends contest_matcher_Matcher {

	function match($value) {
		return $value > $this->matcher->evaluate();
	}

	function __toString() {
		return 'greater than ' . (string)$this->matcher;
	}

}

class contest_matcher_HasKey extends contest_matcher_Matcher {

	function match($value) {
		return array_key_exists($this->matcher->evaluate(), $value);
	}

	function __toString() {
		return 'has key ' . (string)$this->matcher;
	}

}

class contest_matcher_HasValue extends contest_matcher_Matcher {

	function match($value) {
		return array_search($this->matcher->evaluate(), $value) !== false;
	}

	function __toString() {
		return 'has value ' . (string)$this->matcher;
	}

}

function equalTo($value) {
	return new contest_matcher_Equal(new contest_matcher_Value($value));
}

function identicalTo($value) {
	return new contest_matcher_Identical(new contest_matcher_Value($value));
}

function not($value) {
	return new contest_matcher_Not($value);
}

function anyOf($value) {
	$ref = new ReflectionClass('contest_matcher_AnyOf');
	return $ref->newInstanceArgs(func_get_args());
}

function allOf($value) {
	$ref = new ReflectionClass('contest_matcher_AllOf');
	return $ref->newInstanceArgs(func_get_args());
}

function instanceOfClass($value) {
	return new contest_matcher_InstanceOfClass(new contest_matcher_Value($value, 'class'));
}

function greaterThan($value) {
	return new contest_matcher_GreaterThan(new contest_matcher_Value($value));
}

function lessThan($value) {
	return new contest_matcher_LessThan(new contest_matcher_Value($value));
}

function hasKey($value) {
	return new contest_matcher_HasKey(new contest_matcher_Value($value));
}

function hasValue($value) {
	return new contest_matcher_HasValue(new contest_matcher_Value($value));
}

class contest_AssertionFailed extends Exception {}

class contest_ObjectInterceptor {

	protected $instance;
	protected $mock;

	function __construct($instance, $mock) {
		$this->instance = $instance;
		$this->mock = $mock;
	}

	function __call($method, $args) {
		if (!method_exists($this->instance, $method)) {
			throw new Exception("method $method does not exists");
		}
		$returnValue = call_user_func_array(array($this->instance, $method), $args);
		$this->mock->_addMethodCall(new contest_Method($method, $args, $returnValue));
		return new contest_TestEvaluator($returnValue);
	}

}

class contest_TestEvaluator {

	protected $value;

	function __construct($value) {
		$this->value = $value;
	}

	function is($matcher) {
		if (!$matcher->match($this->value)) {
			$v = new contest_matcher_Value($this->value);
			throw new contest_AssertionFailed("expected that " . (string)$v . " is " . (string)$matcher);
		}
		return $this->value;
	}

}

class contest_Method {

	protected $method;
	protected $args;

	function __construct($method, $args, $returnValue) {
		$this->method = $method;
		$this->args = $args;
		$this->returnValue = $returnValue;
	}

	function getReturnValue() {
		return $this->returnValue;
	}

	function verifyCall($method, $args) {
		if ($this->method != $method) {
			throw new Exception("expected call to method($this->method) but got($method)");
		}
		if ($this->args != $args) {
			throw new Exception("expected arguments(" . implode(',', $this->args) . ") but got(" . implode(',', $args) . ")");
		}
	}

}

class contest_Mock {

	protected $callStack = array();

	function __construct() {

	}

	function _addMethodCall($methodCall) {
		array_push($this->callStack, $methodCall);
	}

	function __call($method, $args) {
		$methodCall = array_shift($this->callStack);
		$methodCall->verifyCall($method, $args);
		return $methodCall->getReturnValue();
	}

	function _verify() {
		if (count($this->callStack)) {
			throw new Exception("uncalled methods in mock");
		}
	}
}

