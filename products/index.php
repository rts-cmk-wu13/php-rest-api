<?php
require("../db.php");

function validateID() {
		global $conn;
		if (empty($_GET["id"])) {
		http_response_code(400);
		exit;
	}

	$id = $_GET["id"];

	if (!is_numeric($id)) {
		header("Content-Type: application/json; charset=utf-8");
		http_response_code(400);
		echo json_encode(["message" => "ID is malformed"]);
		exit;
	}

	$id = intval($id, 10);

	$stmt = $conn->prepare("SELECT * FROM products WHERE id = :id");
	$stmt->bindParam(":id", $id, PDO::PARAM_INT);
	$stmt->execute();
	$result = $stmt->fetch(PDO::FETCH_ASSOC);

	if (!is_array($result)) {
		http_response_code(404);
		exit;
	}

	return $id;
}

// HENT ALLE PRODUKTER
if ($_SERVER["REQUEST_METHOD"] === "GET" && empty($_GET["id"])) {
	$limit = isset($_GET["limit"]) ? intval($_GET["limit"]) : 10;
	$offset = isset($_GET["offset"]) ? intval($_GET["offset"]) : 0;

	$stmt = $conn->prepare("SELECT COUNT(id) FROM products");
	$stmt->execute();
	$count = $stmt->fetch(PDO::FETCH_ASSOC);
	
	$stmt = $conn->prepare("SELECT id, name FROM products LIMIT :limit OFFSET :offset");
	$stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
	$stmt->bindParam(":offset", $offset, PDO::PARAM_INT);
	$stmt->execute();
	$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$nextOffset = $offset + $limit;
	$prevOffset = $offset - $limit;

	$next = "http://localhost/webshop/products?offset=$nextOffset&limit=$limit";
	$prev = "http://localhost/webshop/products?offset=$prevOffset&limit=$limit";

	// Tilføj Hypermedia Controls
	for ($i = 0; $i < count($results); $i++) {
		$results[$i]["url"] = "http://localhost/webshop/products?id=" . $results[$i]["id"];
		unset($results[$i]["id"]);
	}

	header("Content-Type: application/json; charset=utf-8");
	$output = [
		"count" => $count["COUNT(id)"],
		"next" => $nextOffset < $count["COUNT(id)"] ? $next : null,
		"prev" => $offset <= 0 ? null : $prev,
		"results" => $results
	];
	echo json_encode($output);
}

// HENT ENKELT PRODUKT
if ($_SERVER["REQUEST_METHOD"] === "GET" && !empty($_GET["id"])) {
	$id = validateID();

	$stmt = $conn->prepare("SELECT 
						products.id, products.name,
    				products.description, products.price,
    				products.weight_in_grams, media.url AS url
			FROM products
				INNER JOIN product_media ON product_media.product_id = products.id
				INNER JOIN media ON media.id = product_media.media_id
			WHERE products.id = :id");
	$stmt->bindParam(":id", $id, PDO::PARAM_INT);
	$stmt->execute();

	$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

	$output = [
		"id" => $results[0]["id"],
		"name" => $results[0]["name"],
		"description" => $results[0]["description"],
		"price" => $results[0]["price"],
		"weight" => $results[0]["weight_in_grams"],
		"media" => [],
	];

	for ($i = 0; $i < count($results); $i++) {
		$output["media"][] = $results[$i]["url"];
	}

	header("Content-Type: application/json; charset=utf-8");
	echo json_encode($output);
}

// OPRET ET PRODUKT
if ($_SERVER["REQUEST_METHOD"] === "POST") {
	$name = $_POST["name"];
	$description = $_POST["description"];
	$price = $_POST["price"];
	$weight = $_POST["weight"];

	$stmt = $conn->prepare("INSERT INTO products (`name`, `description`, `price`, `weight_in_grams`)
													VALUES(:name, :description, :price, :weight)");
	
	$stmt->bindParam(":description", $description);
	$stmt->bindParam(":name", $name);
	$stmt->bindParam(":price", $price, PDO::PARAM_INT);
	$stmt->bindParam(":weight", $weight, PDO::PARAM_INT);

	$stmt->execute();
	http_response_code(201);
}

// REDIGER ET PRODUKT (PUT)
if ($_SERVER["REQUEST_METHOD"] === "PUT") {
	$id = validateID();

	parse_str(file_get_contents("php://input"), $body);

/* 	if (empty($body["name"])) {
		header("Content-Type: application/json; charset=utf-8");
		http_response_code(400);
		echo json_encode(["message" => "missing field 'name'"]);
		exit;
	}
	if (empty($body["description"])) {
		header("Content-Type: application/json; charset=utf-8");
		http_response_code(400);
		echo json_encode(["message" => "missing field 'description'"]);
		exit;
	}
	if (empty($body["price"])) {
		header("Content-Type: application/json; charset=utf-8");
		http_response_code(400);
		echo json_encode(["message" => "missing field 'price'"]);
		exit;
	}
	if (empty($body["weight"])) {
		header("Content-Type: application/json; charset=utf-8");
		http_response_code(400);
		echo json_encode(["message" => "missing field 'weight'"]);
		exit;
	} */

	if (empty($body["name"])
		|| empty($body["description"])
		|| empty($body["price"])
		|| empty($body["weight"])) {
			header("Content-Type: application/json; charset=utf-8");
			http_response_code(400);
			echo json_encode(["message" => "missing field(s). Required fields: 'name', 'description', 'price', 'weight'"]);
			exit;
	}
	
	$stmt = $conn->prepare("UPDATE products
			SET name = :name, description = :description, price = :price, weight_in_grams = :weight WHERE id = :id");
	
	$stmt->bindParam(":description", $body["description"]);
	$stmt->bindParam(":name", $body["name"]);
	$stmt->bindParam(":price", $body["price"], PDO::PARAM_INT);
	$stmt->bindParam(":weight", $body["weight"], PDO::PARAM_INT);
	$stmt->bindParam(":id", $id, PDO::PARAM_INT);

	$stmt->execute();

	$stmt = $conn->prepare("SELECT * FROM products WHERE id = :id");
	$stmt->bindParam(":id", $id, PDO::PARAM_INT);
	$stmt->execute();

	header("Content-Type: application/json; charset=utf-8");
	http_response_code(200);
	echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
}

// SLET ET PRODUKT
if ($_SERVER["REQUEST_METHOD"] === "DELETE") {
	if (empty($_GET["id"])) {
		http_response_code(400);
		exit;
	}

	$id = $_GET["id"];

	$stmt = $conn->prepare("DELETE FROM products WHERE id = :id");
	$stmt->bindParam(":id", $id, PDO::PARAM_INT);

	$stmt->execute();
	http_response_code(204);
}