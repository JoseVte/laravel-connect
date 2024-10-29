<?php

namespace Square1\Laravel\Connect\App\Routes;

use AllowDynamicProperties;
use BadMethodCallException;
use Closure;
use Illuminate\Support\Fluent;
use Illuminate\Support\Str;
use Illuminate\Validation\PresenceVerifierInterface;
use Illuminate\Validation\ValidationData;
use Illuminate\Validation\ValidationRuleParser;
use JsonException;
use RuntimeException;
use Square1\Laravel\Connect\Console\MakeClient;

#[AllowDynamicProperties]
class RequestParamTypeResolver
{
    /**
     * The rules to be applied to the data.
     */
    protected array $rules;

    /**
     * The array of wildcard attributes with their asterisks expanded.
     */
    protected array $implicitAttributes = [];

    /**
     * The array of custom attribute names.
     */
    public array $customAttributes = [];

    /**
     * The array of custom displayable values.
     */
    public array $customValues = [];

    /**
     * All the custom validator extensions.
     */
    public array $extensions = [];

    public array $paramsType = [];

    /**
     * Map of rules types to data types supported by the client.
     */
    protected array $typesMap = [
        'File' => 'UploadedFile',
        'Image' => 'UploadedFile',
        'Integer' => 'int',
        'Boolean' => 'boolean',
        'Date' => 'timestamp',
        'Email' => 'string',
    ];

    /**
     * The validation rules that may be applied to files.
     */
    protected array $fileRules = [
        'File', 'Image', 'Mimes', 'Mimetypes', 'Min',
        'Max', 'Size', 'Between', 'Dimensions',
    ];

    /**
     * The validation rules that imply the field is required.
     */
    protected array $implicitRules = [
        'Required', 'Filled', 'RequiredWith', 'RequiredWithAll', 'RequiredWithout',
        'RequiredWithoutAll', 'RequiredIf', 'RequiredUnless', 'Accepted', 'Present',
    ];

    /**
     * The validation rules which depend on other fields as parameters.
     */
    protected array $dependentRules = [
        'RequiredWith', 'RequiredWithAll', 'RequiredWithout', 'RequiredWithoutAll',
        'RequiredIf', 'RequiredUnless', 'Confirmed', 'Same', 'Different', 'Unique',
        'Before', 'After', 'BeforeOrEqual', 'AfterOrEqual',
    ];

    /**
     * Create a new Validator instance.
     */
    public function __construct(private readonly MakeClient $makeClient, protected array $initialRules)
    {
        $this->setRules($initialRules);
    }

    public function resolve(): void
    {
        // We'll spin through each rule, validating the attributes attached to that
        // rule. Any error messages will be added to the containers with each of
        // the other error messages, returning true if we don't have messages.
        foreach ($this->rules as $attribute => $rules) {
            $attribute = str_replace('\.', '->', $attribute);
            foreach ($rules as $rule) {
                $this->validateAttribute($attribute, $rule);
            }
        }
    }

    /**
     * Validate a given attribute against a rule.
     *
     * @param  string  $attribute
     * @param  string  $rule
     */
    protected function validateAttribute($attribute, $rule): void
    {
        if (is_array($rule)) {
            foreach ($rule as $r) {
                $this->validateAttribute($attribute, $r);
            }

            return;
        }
        $this->makeClient->info("VALIDATING $attribute ->".json_encode($rule), 'vvv');

        //init values
        if (! isset($this->paramsType[$attribute])) {
            $this->paramsType[$attribute] = [];
            $this->paramsType[$attribute]['type'] = 'string';
        }

        [$rule, $parameters] = ValidationRuleParser::parse($rule);

        if ($rule === '') {
            return;
        }

        // First, we will get the correct keys for the given attribute in case the field is nested in
        // an array. Then we determine if the given rule accepts other field names as parameters.
        // If so, we will replace any asterisks found in the parameters with the correct keys.
        if (($keys = $this->getExplicitKeys($attribute))
            && $this->dependsOnOtherFields($rule)
        ) {
            $parameters = $this->replaceAsterisksInParameters($parameters, $keys);
        }

        if ($rule === 'Array') {
            $this->paramsType[$attribute]['array'] = true;
        } elseif ($rule === 'Exists') {
            if (! empty($parameters) && is_array($parameters)) {
                //attempt to resolve the type from the Model class
                $classType = $this->makeClient->getModelClassFromTableName($parameters[0]);
                if (isset($classType)) {
                    $this->paramsType[$attribute]['type'] = $classType;
                } else {
                    //TODO we need to check what type is the key column in the table
                    $this->paramsType[$attribute]['type'] = 'int';
                }
                $this->paramsType[$attribute]['table'] = $parameters[0];
                $this->paramsType[$attribute]['key'] = isset($parameters[1]) ? $parameters[1] : 'id';
            }
        } elseif (isset($this->typesMap[$rule])) {
            $this->paramsType[$attribute]['type'] = $this->typesMap[$rule];
        } else {
            $this->paramsType[$attribute][$rule] = 1;
        }

        $this->makeClient->info("calling $rule ".json_encode($attribute).' '.json_encode($parameters), 'vvv');

        $this->makeClient->info(' ', 'vvv');
        $this->makeClient->info(' ', 'vvv');
    }

    /**
     * Determine if the given rule depends on other fields.
     */
    protected function dependsOnOtherFields(string $rule): bool
    {
        return in_array($rule, $this->dependentRules, true);
    }

    /**
     * Get the explicit keys from an attribute flattened with dot notation.
     *
     * E.g. 'foo.1.bar.spark.baz' -> [1, 'spark'] for 'foo.*.bar.*.baz'
     */
    protected function getExplicitKeys(string $attribute): array
    {
        $pattern = str_replace('\*', '([^\.]+)', preg_quote($this->getPrimaryAttribute($attribute), '/'));

        if (preg_match('/^'.$pattern.'/', $attribute, $keys)) {
            array_shift($keys);
            $this->makeClient->info("getExplicitKeys $attribute => ".json_encode($keys), 'vvv');

            return $keys;
        }

        return [];
    }

    /**
     * Get the primary attribute name.
     *
     * For example, if "name.0" is given, "name.*" will be returned.
     */
    protected function getPrimaryAttribute(string $attribute): string
    {
        $result = $attribute;
        foreach ($this->implicitAttributes as $unparsed => $parsed) {
            if (in_array($attribute, $parsed, true)) {
                $result = $unparsed;
                break;
            }
        }
        $this->makeClient->info("getPrimaryAttribute $attribute => $result", 'vvv');

        return $result;
    }

    /**
     * Replace each field parameter that has asterisks with the given keys.
     *
     *
     * @throws JsonException
     */
    protected function replaceAsterisksInParameters(array $parameters, array $keys): array
    {
        $this->makeClient->info('replaceAsterisksInParameters '.json_encode($parameters, JSON_THROW_ON_ERROR).'=>'.json_encode($keys, JSON_THROW_ON_ERROR), 'vvv');

        return array_map(
            function ($field) use ($keys) {
                return vsprintf(str_replace('*', '%s', $field), $keys);
            },
            $parameters
        );
    }

    /**
     * Determine if the attribute is validatable.
     */
    protected function isValidatable(string $rule, string $attribute, mixed $value): bool
    {
        return $this->presentOrRuleIsImplicit($rule, $attribute, $value) &&
               $this->passesOptionalCheck($attribute) &&
               $this->isNotNullIfMarkedAsNullable($attribute, $value) &&
               $this->hasNotFailedPreviousRuleIfPresenceRule($rule, $attribute);
    }

    /**
     * Determine if the field is present, or the rule implies required.
     */
    protected function presentOrRuleIsImplicit(string $rule, string $attribute, mixed $value): bool
    {
        if (is_string($value) && trim($value) === '') {
            return $this->isImplicit($rule);
        }

        return $this->validatePresent($attribute, $value) || $this->isImplicit($rule);
    }

    /**
     * Determine if a given rule implies the attribute is required.
     *
     * @param  string  $rule
     */
    protected function isImplicit($rule): bool
    {
        return in_array($rule, $this->implicitRules, true);
    }

    /**
     * Determine if the attribute passes any optional check.
     */
    protected function passesOptionalCheck(string $attribute): bool
    {
        if (! $this->hasRule($attribute, ['Sometimes'])) {
            return true;
        }

        $data = ValidationData::initializeAndGatherData($attribute, $this->data);

        return array_key_exists($attribute, $data)
                    || array_key_exists($attribute, $this->data);
    }

    /**
     * Determine if the attribute fails the nullable check.
     */
    protected function isNotNullIfMarkedAsNullable(string $attribute, mixed $value): bool
    {
        if (! $this->hasRule($attribute, ['Nullable'])) {
            return true;
        }

        return ! is_null($value);
    }

    /**
     * Explode the explicit rule into an array if necessary.
     *
     * @throws JsonException
     */
    protected function explodeExplicitRule(mixed $rule): array
    {
        $result = [];

        if (is_string($rule)) {
            $result = explode('|', $rule);
        } elseif (is_object($rule)) {
            $result = [$rule];
        } else {
            $result = $rule;
        }

        $this->makeClient->info("explodeExplicitRule $rule ".json_encode($result, JSON_THROW_ON_ERROR), 'vvv');

        return $result;
    }

    /**
     * Determine if the given attribute has a rule in the given set.
     */
    public function hasRule(string $attribute, array|string $rules): bool
    {
        return ! is_null($this->getRule($attribute, $rules));
    }

    /**
     * Get a rule and its parameters for a given attribute.
     */
    protected function getRule(string $attribute, array|string $rules): ?array
    {
        if (! array_key_exists($attribute, $this->rules)) {
            return null;
        }

        $rules = (array) $rules;

        foreach ($this->rules[$attribute] as $rule) {
            [$rule, $parameters] = ValidationRuleParser::parse($rule);

            if (in_array($rule, $rules, true)) {
                return [$rule, $parameters];
            }
        }
    }

    /**
     * Set the validation rules.
     *
     *
     * @return $this
     *
     * @throws JsonException
     */
    public function setRules(array $rules): static
    {
        $this->initialRules = $rules;

        $this->rules = [];

        $this->addRules($rules);

        return $this;
    }

    /**
     * Parse the given rules and merge them into current rules.
     *
     * @throws JsonException
     */
    protected function addRules(array $rules): void
    {
        foreach ($rules as $param => $rule) {
            //clean up the wildcards
            $param = str_replace('\.\*', '', preg_quote($param));
            //check if param is already set
            if (! isset($this->rules[$param])) {
                $this->rules[$param] = [];
            }
            $exploded = $this->explodeExplicitRule($rule);
            $this->rules[$param][] = array_merge($this->rules[$param], $exploded);
        }
    }

    /**
     * Add conditions to a given field based on a Closure.
     *
     * @return $this
     *
     * @throws JsonException
     */
    public function sometimes(array|string $attribute, array|string $rules, callable $callback): static
    {
        $payload = new Fluent($this->getData());

        if ($callback($payload)) {
            foreach ((array) $attribute as $key) {
                $this->addRules([$key => $rules]);
            }
        }

        return $this;
    }

    /**
     * Register an array of custom validator extensions.
     */
    public function addExtensions(array $extensions): void
    {
        if ($extensions) {
            $keys = array_map('\Illuminate\Support\Str::snake', array_keys($extensions));

            $extensions = array_combine($keys, array_values($extensions));
        }

        $this->extensions = array_merge($this->extensions, $extensions);
    }

    /**
     * Register an array of custom implicit validator extensions.
     */
    public function addImplicitExtensions(array $extensions): void
    {
        $this->addExtensions($extensions);

        foreach ($extensions as $rule => $extension) {
            $this->implicitRules[] = Str::studly($rule);
        }
    }

    /**
     * Register a custom validator extension.
     */
    public function addExtension(string $rule, Closure|string $extension): void
    {
        $this->extensions[Str::snake($rule)] = $extension;
    }

    /**
     * Register a custom implicit validator extension.
     */
    public function addImplicitExtension(string $rule, Closure|string $extension): void
    {
        $this->addExtension($rule, $extension);

        $this->implicitRules[] = Str::studly($rule);
    }

    /**
     * Register an array of custom validator message replacers.
     */
    public function addReplacers(array $replacers): void
    {
        if ($replacers) {
            $keys = array_map('\Illuminate\Support\Str::snake', array_keys($replacers));

            $replacers = array_combine($keys, array_values($replacers));
        }

        $this->replacers = array_merge($this->replacers, $replacers);
    }

    /**
     * Register a custom validator message replacer.
     */
    public function addReplacer(string $rule, Closure|string $replacer): void
    {
        $this->replacers[Str::snake($rule)] = $replacer;
    }

    /**
     * Set the custom messages for the validator.
     */
    public function setCustomMessages(array $messages): void
    {
        $this->customMessages = array_merge($this->customMessages, $messages);
    }

    /**
     * Set the custom attributes on the validator.
     *
     *
     * @return $this
     */
    public function setAttributeNames(array $attributes): static
    {
        $this->customAttributes = $attributes;

        return $this;
    }

    /**
     * Add custom attributes to the validator.
     *
     *
     * @return $this
     */
    public function addCustomAttributes(array $customAttributes): static
    {
        $this->customAttributes = array_merge($this->customAttributes, $customAttributes);

        return $this;
    }

    /**
     * Set the custom values on the validator.
     *
     *
     * @return $this
     */
    public function setValueNames(array $values): static
    {
        $this->customValues = $values;

        return $this;
    }

    /**
     * Add the custom values for the validator.
     *
     *
     * @return $this
     */
    public function addCustomValues(array $customValues): static
    {
        $this->customValues = array_merge($this->customValues, $customValues);

        return $this;
    }

    /**
     * Set the fallback messages for the validator.
     */
    public function setFallbackMessages(array $messages): void
    {
        $this->fallbackMessages = $messages;
    }

    /**
     * Get the Presence Verifier implementation.
     *
     *
     * @throws RuntimeException
     */
    public function getPresenceVerifier(): PresenceVerifierInterface
    {
        if (! isset($this->presenceVerifier)) {
            throw new RuntimeException('Presence verifier has not been set.');
        }

        return $this->presenceVerifier;
    }

    /**
     * Get the Presence Verifier implementation.
     *
     *
     * @throws RuntimeException
     */
    protected function getPresenceVerifierFor(string $connection): PresenceVerifierInterface
    {
        return tap(
            $this->getPresenceVerifier(),
            function ($verifier) use ($connection) {
                $verifier->setConnection($connection);
            }
        );
    }

    /**
     * Set the Presence Verifier implementation.
     */
    public function setPresenceVerifier(PresenceVerifierInterface $presenceVerifier): void
    {
        $this->presenceVerifier = $presenceVerifier;
    }

    /**
     * Call a custom validator extension.
     */
    protected function callExtension(string $rule, array $parameters): ?bool
    {
        $callback = $this->extensions[$rule];

        if ($callback instanceof Closure) {
            return call_user_func_array($callback, $parameters);
        }

        if (is_string($callback)) {
            return $this->callClassBasedExtension($callback, $parameters);
        }
    }

    /**
     * Call a class-based validator extension.
     */
    protected function callClassBasedExtension(string $callback, array $parameters): bool
    {
        [$class, $method] = Str::parseCallback($callback, 'validate');

        return call_user_func_array([$this->container->make($class), $method], $parameters);
    }

    /**
     * Handle dynamic calls to class methods.
     *
     * @return bool|null
     *
     * @throws BadMethodCallException
     */
    public function __call(string $method, array $parameters)
    {
        $rule = Str::snake(substr($method, 8));

        if (isset($this->extensions[$rule])) {
            return $this->callExtension($rule, $parameters);
        }

        throw new BadMethodCallException("Method [$method] does not exist.");
    }
}
