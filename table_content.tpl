<div class="row head-row">
				<div class="col-xs-12 header">
					<div class="title">
						<span> TPC-H </span>
						<p><i>Reports</i></p>
					</div>
				</div>
			</div>

			<div class="row main-content">
				<div class="inside">
				<div class="col-xs-12 text-center">
					<p class="test-description"> Test on [@platformName]</p>
				</div>
				<div class="col-xs-12">
					<p class="accordion details">System details</p>
					<div class="panel">
						<ul>
							<li>Memory [@memory]</li>
							<li>Disk [@disk]</li>
							<li>CPU [@cpu]</li>
						</ul>
					</div>
        		<p class="accordion details">Test details</p>
				<div class="panel">
					<ul>
						<li>Scale factor [@sf]</li>
						<li>Duration [@duration]</li>
						<li>Db Cache Hit Ratio [@hitRatio]</li>
					</ul>
				</div>
    		</div>

				<div class="col-xs-12">
					<div class="tables row">
						<div class="col-xs-12 col-sm-6 left-tables">
							<table border="1">
								<tr>
									<th>Measurement Results</th>
									<th>Value</th>
								</tr>
								<tbody>
									[@measurmentResults]
								</tbody>
							</table>

							<table border="1">
								<tr>
									<th>TPCH Generating step </th>
									<th>Duration </th>
								</tr>
								<tbody>
									[@generatingSteps]
								</tbody>
							</table>

							<table border="1">
								<tr>
									<th>Refresh Query Name</th>
									<th>Duration(Avg)</th>
								</tr>
								<tbody>
									[@refreshQuery]
								</tbody>
							</table>
						</div>

						<div class="col-xs-12 col-sm-6 right-tables">
								<table border="1">
								<tr>
									<th>Query Name </th>
									<th>Duration </th>
									<tbody>
										[@query]
									</tbody>
								</table>
							</div>
						</div>
					</div>
				</div>
			</div>

			<script>
				var acc = document.getElementsByClassName("accordion");
				var i;

				for (i = 0; i < acc.length; i++) {
					acc[i].onclick = function() {
						this.classList.toggle("active");
						var panel = this.nextElementSibling;
						if (panel.style.maxHeight){
							panel.style.maxHeight = null;
						} else {
							panel.style.maxHeight = panel.scrollHeight + "px";
						}
					}
				}
			</script>
