<?php

	include("template.class.php");

	/**
	 * Defines an array for the users.
	 * Each value of the array is an array itself with each user's data.
	 * Typically this would be loaded from a database.
	 */
	$users = array(
		array("username" => "monk3y", "location" => "Portugal")
		, array("username" => "Sailor", "location" => "Moon")
		, array("username" => "Treix!", "location" => "Caribbean Islands")
	);

	/**
	 * Loop through the users and creates a template for each one.
	 * Because each user is an array with key/value pairs defined,
	 * we made our template so each key matches a tag in the template,
	 * allowing us to directly replace the values in the array.
	 * We save each template in the $usersTemplates array.
	 */
	foreach ($users as $user) {
		$row = new Template("table_row.tpl");

		foreach ($user as $key => $value) {
			$row->set($key, $value);
		}
		$usersTemplates[] = $row;
	}

	/**
	 * Merges all our users' templates into a single variable.
	 * This will allow us to use it in the main template.
	 */
	$usersContents = Template::merge($usersTemplates);

	/**
	 * Defines the main template and sets the users' content.
	 */
	$usersList  = new Template("table_content.tpl");
	$usersList->set("users", $usersContents);

	/**
	 * Loads our layout template, settings its title and content.
	 */
	$layout = new Template("layout.tpl");
	$layout->set("title", "Users");
	$layout->set("content", $usersList->output());

	/**
	 * Finally we can output our final page.
	 */
	echo $layout->output();
		file_put_contents("hey.html", $layout->output());

?>
