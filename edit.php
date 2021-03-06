<?php
	error_reporting(E_ERROR | E_WARNING | E_PARSE);

	$error = false;
	$config = array(
		"name" => "",
		"timeFormat" => "h:mm A, MMMM Do",
		"timezone" => "-((new Date()).getTimezoneOffset())",
		"bgcolor" => "white",
		"sortedTimes" => "[[{\"day\":0,\"hour\":10,\"minute\":30}],[],[],[],[],[{\"day\":5,\"hour\":20,\"minute\":15}],[]]",
		"offlineMessage" => "Next stream in {hours}h, {minutes}min and {seconds}s\n\nIn your time: {viewersTime}\n{twitchName}'s time: {streamersTime}",
		"onlineMessage" => "Stream is currently online!"
	);

	if($_SERVER["REQUEST_METHOD"] == "POST")
	{
		require 'Predis/Autoloader.php';
		Predis\Autoloader::register();
		$redis = new Predis\Client();

		$stream = "streamcd_" . $_POST["name"];
		if($redis->exists($stream))
		{
			$config = json_decode($redis->get($stream), true);
			if(!isset($_POST["secret"]) || hash("sha256", $_POST["secret"]) !== $config["secret"])
			{
				header("Location: login.php?s=" . $config["name"]);
				exit();
			}
		}



		function checkParameter($name, $reg, $msg = false)
		{
			global $error, $config;

			if(!isset($_POST[$name]) || !preg_match($reg, $_POST[$name]))
			{
				if(!$error)
					$error = array();

				if($msg)
					$error[$name] = $msg;
				else
					$error[$name] = "Invalid " . $name;
			}
			else
			{
				$config[$name] = $_POST[$name];
			}
		}

		checkParameter("name", "/^[a-zA-Z0-9_ ]+$/");
		checkParameter("timeFormat", "/^[a-zA-Z0-9:, ]+$/");
		checkParameter("timezone", "/^[0-9]+$/");
		checkParameter("bgcolor", "/^(#[0-9a-fA-F]{6})|([a-zA-Z]+)$/");
		checkParameter("sortedTimes", "/^[^<>]+$/");
		checkParameter("onlineMessage", "/^[^<>]+$/");
		checkParameter("offlineMessage", "/^[^<>]+$/");
		checkParameter("secret", "/^.{6,}$/", "Secret must be minimum 6 characters long");

		if($error === false)
		{
			$config["secret"] = hash("sha256", $config["secret"]);
			$stream = "streamcd_" . $config["name"];

			$redis->set($stream, json_encode($config));

			header("Location: index.php?s=" . $config["name"]);
			exit();
		}
	}
?>

<html>
	<head>
		 <link rel="stylesheet" type="text/css" href="static/create.css" />
		  <link rel="stylesheet" type="text/css" href="static/styles.css" />
	</head>
	<body>
		<h1>Stream cooldown creator</h1>
		<table id="main">
			<thread>
				<tr>
					<th>Settings</th>
					<th>Preview</th>
				</tr>
			</thead>
			<tbody>
				<tr>
					<td>
						<form method="POST" action="edit.php">
							<?php
								if($error !== false)
								{
									?>
										<fieldset>
											<legend>Errors</legend>
											<table class="inputTable" id="errorList">
												<?php
													foreach($error as $key => $msg)
													{
														if($msg)
															echo("<tr><td>$msg</td></tr>");
													}
												?>
											</table>
										</fieldset>
									<?php
								}
							?>
							<fieldset>
								<legend>Basic Information</legend>
								<table class="inputTable">
									<tr>
										<td>Twitch name</td>
										<td><input id="twitchName" type="text" name="name" onchange="updatePreview()" /></td>
									</tr>
									<tr>
										<td>Time format</td>
										<td><input id="timeFormat" type="text" name="timeFormat" onchange="updatePreview()" /></td>
									</tr>
									<tr>
										<td>Timezone </td>
										<td><input id="timezone" type="number" name="timezone" onchange="updatePreview()" /></td>
									</tr>
									<tr>
										<td>Background </td>
										<td><input id="bgcolor" type="text" name="bgcolor" onchange="updatePreview()" /></td>
									</tr>
									<tr>
										<td colspan="2"><hr /></td>
									</tr>
									<tr>
										<td colspan="2">
											See <a href="timeformat.html">this table</a> for a list of supported insertions in Time format.
											<br />
											Timezone is the offset in minutes to UTC. This should be set correctly automatically
											<br />
											Background can be a color name or hexcode
										</td>
									</tr>
								</table>
							</fieldset>

							<fieldset>
								<legend>Stream Times</legend>

								<input type="hidden" id="sortedTimes" name="sortedTimes" />
								<table class="inputTable">
									<thead>
										<th>Day</th>
										<th>Hour</th>
										<th>Minute</th>
										<th>Delete</th>
									</thead>
									<tbody>
										<tr>
											<td>
												<select onchange="updateStreamTime(this.parentNode.parentNode)">
													<option value="1">Monday</option>
													<option value="2">Tuesday</option>
													<option value="3">Wednesday</option>
													<option value="4">Thursday</option>
													<option value="5" selected>Friday</option>
													<option value="6">Saturday</option>
													<option value="0">Sunday</option>
												</select>
											</td>
											<td><input type="number" value="20" onchange="updateStreamTime(this.parentNode.parentNode)" /></td>
											<td><input type="number" value="15" onchange="updateStreamTime(this.parentNode.parentNode)" /></td>
											<td><button type="button" onclick="delStreamTime(this.parentNode.parentNode)"><img src="static/trash.svg" /></button>
										</tr>
										<tr>
											<td colspan="4">
												<button type="button" id="addStreamTimeBtn" onclick="addStreamTime(this.parentNode.parentNode)">Add</button>
											</td>
										</tr>
									</tbody>
								</table>
							</fieldset>

							<fieldset>
								<legend>Online Text</legend>
								<textarea id="onlineMessage" name="onlineMessage" onchange="updatePreview()"></textarea>
							</fieldset>

							<fieldset>
								<legend>Offline Text</legend>
								<textarea id="offlineMessage" name="offlineMessage" onchange="updatePreview()"></textarea>
							</fieldset>

							<fieldset>
								<legend>Text insertions</legend>
								The following insertions can be used in "Online Text" and "Offline Text"
								<table class="inputTable">
									<thead>
										<tr>
											<th>Name</th>
											<th>Description</th>
										</tr>
									</thead>
									<tbody>
										<tr>
											<td>{twitchName}</td>
											<td>Twitch name entered above</td>
										</tr>
										<tr>
											<td>{hours}</td>
											<td>Hours until next stream</td>
										</tr>
										<tr>
											<td>{minutes}</td>
											<td>Minutes until next stream</td>
										</tr>
										<tr>
											<td>{seconds}</td>
											<td>Seconds until next stream</td>
										</tr>
										<tr>
											<td>{viewersTime}</td>
											<td>Next stream in the time of the current viewer</td>
										</tr>
										<tr>
											<td>{streamersTime}</td>
											<td>Next stream in the time of the streamer</td>
										</tr>
										<tr>
											<td>{currentTime}</td>
											<td>Current time</td>
										</tr>
										<tr>
											<td>{h1}, {h2}, ...</td>
											<td>Beginning of a header (makes the text larger)</td>
										</tr>
										<tr>
											<td>{/h1}, {/h2}, ...</td>
											<td>Ending of a header (makes the text normal again)</td>
										</tr>
										<tr>
											<td>{hr}</td>
											<td>Horizontal line</td>
										</tr>
									</tbody>
								</table>
							</fieldset>

							<fieldset>
								<legend>Finalize</legend>
								<table class="inputTable">
									<tr>
										<td>Secret</td>
										<td><input type="password" name="secret" value="<?= $config["secret"] ?>" /></td>
									</tr>
									<tr>
										<td colspan="2">You can later edit the above settings using your secret. Its like a password</td>
									</tr>
									<tr>
										<td colspan="2"><input type="submit" value=" save " /></td>
									</tr>
								</table>
							</fieldset>
						</form>
					</td>

					<td>
						<div id="output">
							<noscript>Please enable Javascript</noscript>
						</div>
					</td>
				</tr>
			<tbody>
		</table>

		<script type="text/javascript">
			var streamOnline = false;
			var twitchName = "<?= $config["name"] ?>";
			var timeFormat = "<?= $config["timeFormat"] ?>";
			var streamerOffset = <?= $config["timezone"] ?>;
			var offlineMessage = <?= json_encode($config["offlineMessage"] ); ?>;
			var onlineMessage = <?= json_encode($config["onlineMessage"] ); ?>;
			var sortedTimes = <?= $config["sortedTimes"] ?>;
			var bgcolor = <?= json_encode($config["bgcolor"]) ?>;
		</script>
		<script type="text/javascript" src="static/moment.js"></script>
		<script type="text/javascript" src="static/edit.js"></script>
		<script type="text/javascript" src="static/main.js"></script>
	</body>
</html>
