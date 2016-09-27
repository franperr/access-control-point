<?php
/* Access Control Point to Nano */
/* V1.0 of the ACP API */

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Silex\Application;

// Initialisation of controller
$acp = $app['controllers_factory'];

/**
 * Get dictionnary of properties of entities from DB
 */
function getPropertiesDescription( Application $app, $entityType ) {

    $sql = "SELECT name, description, hasEntityType FROM dictionnary WHERE entityType=?";
    return $app['db']->fetchAll($sql, array( $entityType ) );
}


/**
 * Return false if entityType is defined in the parameters file
 * Ohterwise it returns an array with an error message
 */
function invalidEntityType( Application $app, $entityType ) {
    if ( ! in_array( $entityType, $app['parameters']['metadata']['entityType'] ) ) {
        // If entity type doesn't exists, return an error
        $error["code"]    = 2;
        $error["message"] = "EntityType unavailable";
        $error["fields"]  = $entityType;
        return $error;
    }
    return false;
}


$acp->get('/entities/{entityType}/count', function(Application $app, Request $request, $entityType) {
    // Log of the path access
    $app['monolog']->addInfo( "Entities count (".$entityType.")" );

    if ( $error = invalidEntityType( $app, $entityType ) ) { return $app->json( $error, 404 ); }

    $sql = "SELECT COUNT(*) AS nb FROM " . $entityType ;

    $res = $app['db']->fetchAll( $sql );

    $response["total"] = (int)$res[0]["nb"];
    $response["max_per_page"] = $app['parameters']['db.options']['max_limit'];

    return $app->json( $response );
});


$acp->get('/entities/{entityType}/{id}', function(Application $app, Request $request, $entityType, $id ) {
    // Log of the path access
    $app['monolog']->addInfo( "Entity (".$entityType."/".$id.")" );

    if ( $error = invalidEntityType( $app, $entityType ) ) { return $app->json( $error, 404 ); }

    $entity["dataset"]     = $app['parameters']['metadata']['dataset'];
    $entity["version"]     = $app['parameters']['metadata']['version'];
    $entity["entity_type"] = $entityType;
    $entity["offset"] = 0;
    $entity["limit"] = 1;

    $entity["property_description"] = getPropertiesDescription( $app, $entityType );

    $sql = "SELECT * FROM " . $entityType . " WHERE id=?";

    $results = $app['db']->fetchAll($sql, array( $id ) );

    foreach( $results as $res ) {
        foreach( $res as $property => $value ) {
            if ( $property === "id" ) {
                $instance["id"] = $value;
            } else {
                $prop["property"] = $property;
                $prop["value"]    = $value;
                $instance["property_values"][] = $prop;
            }
        }
        $entity["instances"][] = $instance;
        unset( $instance );
    }

    if ( count($entity["instances"]) == 0 ) {
        $error["code"]    = 3;
        $error["message"] = "No instance found";
        $error["field"]   = $entityType . (isset($id)?" $id":"");
        return $app->json( $error, 404 );
    }

    return $app->json($entity);

});

$acp->get('/entities/{entityType}', function(Application $app, Request $request, $entityType ) {
    // Log of the path access
    $app['monolog']->addInfo( "Entities (".$entityType.")" );

    if ( $error = invalidEntityType( $app, $entityType ) ) { return $app->json( $error, 404 ); }

    $entity["dataset"]     = $app['parameters']['metadata']['dataset'];
    $entity["version"]     = $app['parameters']['metadata']['version'];
    $entity["entity_type"] = $entityType;

    $offset = (int)$request->get('offset');
    $limit  = (int)$request->get('limit');
    if ( $limit == 0 ) $limit = $app['parameters']['db.options']['default_limit'];
    $limit = min( $limit, $app['parameters']['db.options']['max_limit'] );
    $entity["offset"] = $offset;
    $entity["limit"] = $limit;

    $entity["property_description"] = getPropertiesDescription( $app, $entityType );

    $sql = "SELECT * FROM " . $entityType . " LIMIT " . $offset . "," . $limit;

    $results = $app['db']->fetchAll( $sql );

    foreach( $results as $res ) {
        foreach( $res as $property => $value ) {
            if ( $property === "id" ) {
                $instance["id"] = $value;
            } else {
                $prop["property"] = $property;
                $prop["value"]    = $value;
                $instance["property_values"][] = $prop;
            }
        }
        $entity["instances"][] = $instance;
        unset( $instance );
    }

    if ( count($entity["instances"]) == 0 ) {
        $error["code"]    = 3;
        $error["message"] = "No instance found";
        $error["field"]   = $entityType . (isset($id)?" $id":"");
        return $app->json( $error, 404 );
    }

    return $app->json($entity);
});


$acp->get('/entityTypes', function(Application $app, Request $request) {
    $app['monolog']->addInfo( "EntityTypes" );

    $entitiesList = $app['parameters']['metadata']['entityType'];

    foreach( $entitiesList as $oneEntity ) {
        // Creation of one entity type
        $entity['name'] = $oneEntity;
        $entity['path'] = "http://".$_SERVER['HTTP_HOST']."/v1.0/entities/".$oneEntity;

        $sql = "SELECT description FROM entities WHERE entity=?";
        $result = $app['db']->fetchAll( $sql, array( $oneEntity ) );

        $entity['description'] = $result[0]['description'];
        // Addition of the entity type to the response
        $response[] = $entity;
    }

    // Return of entity types list in json format
    return $app->json( $response );
});

$acp->get('/metadata', function(Application $app, Request $request) {
    $app['monolog']->addInfo( "Metadata" );

    $meta["title"]                = $app['parameters']['metadata']['dataset'];
    $meta["description"]          = $app['parameters']['metadata']['description'];
    $meta["creationDate"]         = $app['parameters']['metadata']['creationDate'];
    $meta["contact_person"]       = $app['parameters']['metadata']['contact_person'];
    $meta["contact_organisation"] = $app['parameters']['metadata']['contact_organisation'];
    $meta["contact_email"]        = $app['parameters']['metadata']['contact_email'];

    return $app->json( $meta );
});

return $acp;