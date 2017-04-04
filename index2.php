<?php
include 'commands.php';
include 'classes/index.php';
?>
<style>
body { background-color:#e0ffe0; }
textarea { background-color:#d0ffd0; color:#040; font-weight:bold; font-family:"freemono",sans-serif;}
pre {
	 background-color:#d0ffd0; color:#040; font-weight:bold; font-family:"freemono",sans-serif; font-size:12px;
}
th,td {
	font-size:12px;
}
#exchanges { background-color:#040; color:#efe; }
#out {
	background-color:#efe;
}
table { border-collapse: collapse; }

tr.exchange {
	border:3px solid #484;
}
tr.exchange > td {
	padding:15px;
}
tr.exchange:nth-last-child(1), tr.exchange:nth-last-child(2) {
	background-color:#fff;
}
.flat {
	font-size:12px;
	font-weight:500;
}
</style>
<body>
<script type="text/javascript" src="jquery-3.2.0.min.js"></script>
<table>
<tr><td><b>FadelaBOT</b> Version 0.2.0<br/> &copy; 2015 Lockett Analytical Systems</td></tr>
<tr>
<td width="200px" height="200px" id='img_frame' style="position:relative";>
<img style="display:inline-table;position:absolute;top:0;left:0;display:auto" src='avatar/draw.php?l=0&p=0&d=0' id='avatar'>
</td>
<td>
<textarea id='mes' rows=10 cols=75>
</textarea><br/>
<input type='submit' value="Submit Message" name='go' id='send'>
</td></tr>
<tr>
<td colspan=2>
<div id="exchanges" style="display: none; font-weight:bold; width:810px;"><div style='border:1px solid #000; width:inherit;'><span style='display:table-row;'>Exchanges</span></div>
<div style='height:400px; overflow:scroll; overflow-x: hidden; display:inline-block;' id='out'>
<table id='exc' width=800px>
<tr></tr>
</table>
</div>
</div>
</td>
</tr>
</table>
</body>
<script>
<?php
$kernel = kernel::load('kernel.dat');
$kernel = base64_encode(serialize($kernel));
echo "var kernel = '$kernel';\n";
?>
var last_l = 0;
var last_p = 0;
var last_d = 0;
var it = 0.0;
function change_avatar(l,p,d,steps,i) {
	while(i < steps) {
		
		$('#img_frame').append('<img style="display:inline-table;position:absolute;top:0;left:0;display:auto" src="avatar/draw.php?l='+last_l+'&p='+last_p+'&d='+last_d+'&nonce='+Math.random()+'">');

		l_diff = (l - last_l) * (1/(steps-i));
		p_diff = (p - last_p) * (1/(steps-i));
		d_diff = (d - last_d) * (1/(steps-i));

		last_l += l_diff;
		last_p += p_diff;
		last_d += d_diff;

		i++;
	}
}
var last = null;
$(document).ready(function()
{
	$('#send').click(function(){
		var mes = $('#mes').val();
		var data = {'kernel':kernel,'msg':mes};
		$.ajax({
			type:"POST",
			url:"api.php",
			data:data,
			success:function(e){
				$('#exchanges').show();
				it++;
				//console.log(e);
				kernel = e.kernel;
				hist = e.hist;
				$('#mes').val('');
				for(i = 0; i < hist.length; i++) {
					$('#exc tr:last').after('<tr class="exchange"><th width=20% valign=top>'+hist[i][0]+'</td><td><span class="flat">'+hist[i][2]+'</span><br/><pre>'+hist[i][1]+'</pre></td></tr>');
					if(i == hist.length-1) {
						l = e.status[e.status.length-1]['l']['avatar'];
						p = e.status[e.status.length-1]['p']['avatar'];
						d = e.status[e.status.length-1]['d']['avatar'];
						clearTimeout(last);
						last = setTimeout(change_avatar(l,p,d,20,0),0);
					}
				}
				$('#out').animate({"scrollTop": $('#out	')[0].scrollHeight}, "slow");
			},
			dataType:"json"
		});
	});
});

</script>
