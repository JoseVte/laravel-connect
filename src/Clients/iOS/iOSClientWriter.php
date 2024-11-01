<?php

namespace Square1\Laravel\Connect\Clients\iOS;

use DOMDocument;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Str;
use Square1\Laravel\Connect\Clients\ClientWriter;
use Square1\Laravel\Connect\Clients\Deploy\GitDeploy;
use Square1\Laravel\Connect\Console\MakeClient;
use Square1\Laravel\Connect\Model\ModelAttribute;

class iOSClientWriter extends ClientWriter
{
    public function __construct(MakeClient $client)
    {
        parent::__construct($client);
    }

    /**
     * @throws FileNotFoundException
     * @throws \Throwable
     * @throws \DOMException
     */
    public function outputClient(): void
    {
        $this->info('------ RUNNING iOS CLIENT WRITER ------');

        $appVersion = $this->appVersion();
        $path = $this->buildSwiftFolder();

        //now that path is set prepares git for deployment
        // pull previous version
        $git = new GitDeploy(
            env('IOS_GIT_REPO'),
            $this->client()->baseBuildPath.'/iOS/',
            env('IOS_GIT_BRANCH')
        );

        $git->setDisabled(env('IOS_GIT_DISABLED', true));
        $git->init();

        $tableMap = array_merge([], $this->client()->tableMap);

        $members_test = [];
        //xcdatamodeld package content
        $xml = new DOMDocument;
        $xmlTemplate = $this->client()->files->get(__DIR__.'/schematemplate.xml');
        $xml->loadXML($xmlTemplate);

        $xmlModel = $xml->getElementsByTagName('model')->item(0);
        $xmlElements = $xml->createElement('elements');

        foreach ($this->client()->classMap as $classMap) {
            $routes = $classMap['routes'] ?? [];
            $inspector = $classMap['inspector'];
            $className = $inspector->classShortName();
            $primaryKey = $inspector->primaryKey();
            $classPath = $inspector->endpointReference();
            $this->info($className);

            //loop over the tables and match members and types
            $members = $this->buildSwiftMembers(
                array_merge(
                    $inspector->getDynamicAttributes(),
                    $tableMap[$inspector->tableName()]['attributes']
                ),
                $className
            );

            $members_test[$className] = $members;

            //create xml for coredata schema, start with the Entity element
            $coredata_entity = $xml->createElement('entity');
            $coredata_entity->setAttribute('name', $className);
            // no need for this or we have conflicts
            //$coredata_entity->setAttribute("codeGenerationType","class");
            $coredata_entity->setAttribute('representedClassName', $className);
            $coredata_entity->setAttribute('syncable', 'YES');

            //set userInfo dictionary for the entitiy itself
            //laravel.model.path
            $entityUserInfo = $xml->createElement('userInfo');
            $modelPathElement = $xml->createElement('entry');
            $modelPathElement->setAttribute('key', 'laravel.model.path');
            $modelPathElement->setAttribute('value', $classPath);
            // add to main Entity
            $entityUserInfo->appendChild($modelPathElement);

            //setting attributes to the entity, ids strings ecc ecc.
            // <attribute name="content" optional="YES" attributeType="String" syncable="YES"/>

            // add a boolean flag the app uses to know if it only has the primary key for this
            // object or if the full json was parsed at some stage. This is useful for to-1 relations
            // where we only have the foreign key
            // <attribute name="hasData" optional="YES" attributeType="Boolean"
            // defaultValueString="NO" usesScalarValueType="YES" syncable="YES"/>

            $newElement = $xml->createElement('attribute');
            $newElement->setAttribute('name', 'hasData');
            $newElement->setAttribute('optional', 'NO');
            $newElement->setAttribute('attributeType', 'Boolean');
            $newElement->setAttribute('defaultValueString', 'NO');
            $newElement->setAttribute('usesScalarValueType', 'YES');
            $newElement->setAttribute('syncable', 'YES');

            // add to main Entity
            $coredata_entity->appendChild($newElement);

            foreach ($members as $member) {
                $newElement = $xml->createElement('attribute');
                $userInfo = $xml->createElement('userInfo');

                if ($member['primaryKey']) {
                    // ="0" ="YES" syncable="YES"/>
                    $el = $xml->createElement('entry');
                    $el->setAttribute('key', 'laravel.model.primaryKey');
                    $el->setAttribute('value', 'YES');
                    $userInfo->appendChild($el);

                    //laravel.cd.primary.key
                    //store in the main entity the name of the primary key
                    //and then set the user info to the main entity
                    $coreDataPrimaryKeyElement = $xml->createElement('entry');
                    $coreDataPrimaryKeyElement->setAttribute('key', 'laravel.cd.primary.key');
                    $coreDataPrimaryKeyElement->setAttribute('value', $member['varName']);
                    $entityUserInfo->appendChild($coreDataPrimaryKeyElement);
                    $coredata_entity->appendChild($entityUserInfo);
                }

                $el = $xml->createElement('entry');
                $el->setAttribute('key', 'laravel.json.key');
                $el->setAttribute('value', $member['json_key']);
                $userInfo->appendChild($el);

                $newElement->appendChild($userInfo);

                $newElement->setAttribute('attributeType', $member['xmlType']);
                $newElement->setAttribute('name', $member['varName']);

                ///set extra attributes
                foreach ($member['extraTypeAttributes'] as $attrName => $attrValue) {
                    $newElement->setAttribute($attrName, $attrValue);
                }

                if (isset($newElement)) {
                    $newElement->setAttribute('optional', $member['primaryKey'] ? 'NO' : 'YES');
                    $coredata_entity->appendChild($newElement);
                }
            }

            //seeting relationships to the entity
            $relations = $this->buildCoreDataRelations($inspector->relations());

            foreach ($relations as $relation) {
                $newElement = $xml->createElement('relationship');
                $newElement->setAttribute('name', $relation['varName']);
                $newElement->setAttribute('destinationEntity', $relation['relatedClass']);
                $newElement->setAttribute('deletionRule', 'Nullify');

                $userInfo = $xml->createElement('userInfo');
                $newElement->appendChild($userInfo);

                if ($relation['many']) {
                    $newElement->setAttribute('toMany', 'YES');
                } else {
                    $newElement->setAttribute('maxCount', '1');
                    $newElement->setAttribute('optional', 'YES');
                    $el = $xml->createElement('entry');
                    $el->setAttribute('key', 'laravel.model.foreignKey');
                    $el->setAttribute('value', $relation['key']);
                    $userInfo->appendChild($el);
                }

                $el = $xml->createElement('entry');
                $el->setAttribute('key', 'laravel.json.key');
                $el->setAttribute('value', $relation['name']);
                $userInfo->appendChild($el);

                $coredata_entity->appendChild($newElement);
            }

            $xmlModel->appendChild($coredata_entity);

            //this is just for the visual editor
            $element = $xml->createElement('element');
            $element->setAttribute('name', $className);

            $element->setAttribute('positionX', '261');
            $element->setAttribute('positionY', '161');

            $element->setAttribute('width', '150');
            $element->setAttribute('height', '100');

            $xmlElements->appendChild($element);

            $endpoints = $this->buildSwiftRoutes($routes);
            unset($tableMap[$inspector->tableName()]);

            $swift = view('ios::master', compact('appVersion', 'classPath', 'relations', 'members', 'package', 'className', 'primaryKey', 'endpoints'))->render();
            $this->client()->files->put($path.'/'.$className.'+CoreDataClass.swift', $swift);

        }
        $xmlModel->appendChild($xmlElements);
        $this->buildXCDatamodeld($xml);
        $this->client()->dumpObject('members_test', $members_test);

        //build settings
        $roothPath = $this->pathComponentsAsArrayString();
        $appName = $this->appName();
        $swift = view('ios::settings_master', compact('appVersion', 'roothPath', 'appName'))->render();
        $this->client()->files->put($path.'/AppSettings.swift', $swift);

        // deliver to the mobile developer
        $git->push();
    }

    private function buildSwiftRoutes($routes)
    {
        $requests = [];

        foreach ($routes as $route) {
            $allowedMethods = array_diff($route['methods'], ['HEAD']);
            foreach ($allowedMethods as $method) {
                $request = $this->buildSwiftRoute($method, $route);
                if (count($allowedMethods) > 1) {
                    $request['requestName'] = $request['requestName']."_$method";
                }
                $requests[] = $request;
            }
        }

        return $requests;
    }

    private function buildSwiftRoute($method, $route)
    {
        $requestParams = null;
        $requestParamsMap = null;

        foreach ($route['params'] as $paramName => $param) {
            $type = null;
            if (isset($param['table'])) {
                $type = $this->resolveTableNameToSwiftType($param['table']);
            }
            //if no table type we use the route type
            if (! isset($type)) {
                $type = $this->resolveToSwiftType($param['type']);
            }

            if (isset($param['array']) && $param['array'] == true) {
                $type = "ArrayList<$type>";
            }

            //buidling the method signature
            if (! empty($requestParams)) {
                $requestParams = $requestParams.', ';
            }
            $requestParams = $requestParams."$type $paramName";

            if (! empty($requestParamsMap)) {
                $requestParamsMap = $requestParamsMap.',';
            }
            $requestParamsMap = $requestParamsMap."\"$paramName\",$paramName";
        }

        $request = [];
        $request['requestMethod'] = $method;
        $request['requestUri'] = $route['uri'];
        $request['requestName'] = $route['methodName'];
        $request['paginated'] = $route['paginated'] == true ? 'true' : 'false';
        $request['requestParams'] = $requestParams;
        $request['requestParamsMap'] = $requestParamsMap;

        return $request;
    }

    //@[@"type", @"description", @"signed"];
    public function getSwiftVariableName($attributeName, $className)
    {
        $prefix = config('connect.clients.ios.prefix');
        if (empty($prefix)) {
            if ($attributeName === 'description'
                || $attributeName === 'type'
                || $attributeName === 'signed'
            ) {
                $attributeName = $className.'_'.$attributeName;
            }

            return lcfirst(Str::studly($attributeName));
        }

        return $prefix.Str::studly($attributeName);
    }

    private function buildSwiftMembers($attributes, $className): array
    {
        $members = [];
        $prefix = config('connect.clients.ios.prefix', 'cn');
        foreach ($attributes as $attribute) {
            $attribute = is_array($attribute) ? $attribute[0] : $attribute;
            $this->info("$attribute", 'vvv');

            //this save us from members that use language specific keywords as name
            $varName = $this->getSwiftVariableName($attribute->name, $className);
            $name = Str::studly($attribute->name);

            $allowedValues = [];
            $type = $this->resolveType($attribute);

            if (is_array($attribute->allowed)) {
                $allowedValues = $attribute->allowed;
                $values = implode(',', $allowedValues);
                $this->info(" allowed values: $values", 'vvv');
            }

            //special type enum is treated in a different way.
            // we define a typealias String to map allowed values
            if ($type === 'enum' && empty($allowedValues)) {
                //for some reason we have no allowed values? Go back to String
                $type = 'String';
            }

            $json_key = $attribute->name;
            $extraTypeAttributes = []; // extra values for the xml core data , for example the transformable for UploadedFiles
            $xmlType = $this->resolveTypeForCoreDataXML($attribute, $extraTypeAttributes);
            $collection = $attribute->collection;
            $dynamic = $attribute->dynamic; //those have no setter! are from the append of the model array
            $primaryKey = $attribute->primaryKey();

            $references = $attribute->foreignKey ?? null;
            if (! empty($type)) {
                $members[] = compact('json_key', 'dynamic', 'xmlType', 'extraTypeAttributes',
                    'collection', 'varName', 'name', 'type', 'allowedValues', 'primaryKey', 'references');
            }

        }

        return $members;
    }

    public function buildRoutes($routes)
    {
        if (empty($routes)) {
            return [];
        }

        $swiftRoutes = [];
        foreach ($routes as $route) {
        }

        return $swiftRoutes;
    }

    public function buildRoute($route)
    {
        $swiftRoute = [];

        $params = [];

        foreach ($route['params'] as $paramName => $param) {
            $current = [];
            $current['name'] = $paramName;
            $current['type'] = isset($param['table']) ?
                    $this->resolveTableNameToSwiftType($param['table']) : null;
            if (is_null($current['type'])) {
                $current['type'] = $this->resolveToSwiftType($param['type']);
            }

            $current['array'] = isset($param['array']);

            $params[] = $current;
        }

        return $swiftRoute;
    }

    /**
     * @param  mixed  $attribute,  string or ModelAttribute
     */
    public function resolveType(mixed $attribute): ?string
    {
        if ($attribute instanceof ModelAttribute) {
            //if on ins not empty this is a foreign key to another table.
            // in this case we check if there us a model class associated with that table
            // and we use it as the type for this
            if (! empty($attribute->on)) {
                if (isset($this->client()->tableInspectorMap[$attribute->on])) {//$attribute->isRelation() == TRUE){
                    ///so this is a relation, lets get the 'on' value and find what class this relates to
                    $modelInspector = $this->client()->tableInspectorMap[$attribute->on];
                    if (! empty($modelInspector)) {
                        return $modelInspector->classShortName();
                    }

                    return null; ///this was a relation with a model that is not exposed
                }

                return null;
            }
            $attribute = $attribute->type;
        }

        return $this->resolveToSwiftType($attribute);
    }

    public function resolveTableNameToSwiftType($table)
    {
        $modelInspector = $this->client()->tableInspectorMap[$table];
        if (! empty($modelInspector)) {
            return $modelInspector->classShortName();
        }

        return null;
    }

    private function resolveTypeForCoreDataXML($type, &$attributes = [])
    {
        if ($type instanceof ModelAttribute) {
            if (! empty($type->on)) {
                if (isset($this->client()->tableInspectorMap[$type->on])) {//$attribute->isRelation() == TRUE){
                    ///so this is a relation, lets get the 'on' value and find what class this relates to
                    $modelInspector = $this->client()->tableInspectorMap[$type->on];
                    if (! empty($modelInspector)) {
                        return $modelInspector->classShortName();
                    }

                    return null; ///this was a relation with a model that is not exposed
                }

                return null;
            }

            $type = $type->type;
        }

        if ($type === 'text'
            || $type === 'char'
            || $type === 'string'
             || $type === 'enum'
        ) {
            return 'String';
        }

        if ($type === 'timestamp'
            || $type === 'date'
            || $type === 'dateTime'
        ) {
            return 'Date';
        }

        if ($type === 'integer' || $type === 'int') {
            return 'Integer 64';
        }

        if ($type === 'float') {
            return 'Float';
        }

        if ($type === 'double') {
            return 'Double';
        }

        if ($type === 'boolean') {
            return 'Boolean';
        }

        if ($type === 'image') {
            $attributes['valueTransformerName'] = 'UploadedImageCoreDataTransformer';

            return 'Transformable';
        }

        return $type;
    }

    public function resolveToSwiftType($type): string
    {
        if ($type === 'text'
            || $type === 'char'
            || $type === 'string'
            // || $type == 'enum' //return enum to swift type and deal with allowed values
        ) {
            return 'String';
        }

        if ($type === 'timestamp'
            || $type === 'date'
            || ($type === 'dateTime')
        ) {
            return 'NSDate';
        }

        if ($type === 'integer' || $type === 'int') {
            return 'Int64';
        }

        if ($type === 'float') {
            return 'Float';
        }

        if ($type === 'double') {
            return 'Double';
        }

        if ($type === 'boolean') {
            return 'Bool';
        }

        if ($type === 'image') {
            return 'UploadedImage';
        }

        return $type;
    }

    private function buildCoreDataRelations($relations): array
    {
        $results = [];

        foreach ($relations as $relationName => $relation) {

            if (! isset($this->client()->classMap[$relation['related']])) {
                continue;
            }

            $relatedClass = $this->client()->classMap[$relation['related']]['inspector']->classShortName();
            $varName = $relationName;
            $name = $relationName;
            $type = $relatedClass; //$relation['many'] ? "NSSet" : $relatedClass;
            $hasSetter = false;
            //$type = $type."<$relatedClass>";
            $many = $relation['many'];
            $key = $relation['foreignKey'];
            $results[$varName] = compact('hasSetter', 'varName', 'name', 'type', 'relatedClass', 'key', 'many');
        }

        return $results;
    }

    private function buildSwiftFolder(): string
    {
        $path = $this->client()->baseBuildPath.'/iOS/';

        $this->client()->initAndClearFolder($path);

        //laravel_connect_test_app.xcdatamodeld

        return $path;
    }

    private function buildXCDatamodeld(DOMDocument $model): void
    {
        $path = $this->client()->baseBuildPath.'/iOS/'.config('connect.clients.ios.data_model_name').'.xcdatamodeld';

        $currentVersion = config('connect.clients.ios.data_model_name').'.xcdatamodel';
        $this->client()->initAndClearFolder($path.'/'.$currentVersion);
        $this->client()->files->put($path.'/'.$currentVersion.'/contents', $model->saveXML());

        $plist = view('ios::xcdatamodel_version', compact('currentVersion'))->render();
        $this->client()->files->put($path.'/.xccurrentversion', $plist);
        //laravel_connect_test_app.xcdatamodel
    }
}
