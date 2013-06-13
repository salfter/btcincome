<?php
  include_once("phpMyGraph5.0.php");

  //$tz=$_GET["tz"];
  //if ($tz=="")
    $tz="US/Pacific";

  date_default_timezone_set($tz);
 
  // convert time in GMT to local time
 
  function convert_time($time, $timezone)
  {
    $gmtime=new DateTime($time, new DateTimeZone("GMT"));
    $loc=new DateTimeZone($tz);
    $off=$loc->getOffset($gmtime);
    $m=($off<0)?-1:1;
    $int=new DateInterval("PT".$m*$off."S");
    if ($m==-1)
      $int->invert=1;
    return $dt->add($int)->getTimestamp();
  }
 
  //echo "123456789 ".convert_time(123456789, $tz)."\n";
  //return;

  // recursive-descent functions to verify origin of a deposit

  function check_tx_by_hash($txhash, $srcaddr)
  {
    $conn=curl_init("http://blockchain.info/rawtx/".$txhash);
    curl_setopt($conn, CURLOPT_RETURNTRANSFER, true);
    $data=json_decode(curl_exec($conn));
    curl_close($conn);

    foreach ($data->inputs as $in)
      if (array_key_exists("prev_out", $in))
        return check_tx_by_index($in->prev_out->tx_index, $srcaddr);
    else
        foreach ($data->out as $out)
          if ($out->addr==$srcaddr)
            return true;
    return false;
  }
 
  function check_tx_by_index($txindex, $srcaddr)
  {
    $conn=curl_init("http://blockchain.info/tx-index/".$txindex."?format=json");
    curl_setopt($conn, CURLOPT_RETURNTRANSFER, true);
    $data=json_decode(curl_exec($conn));
    curl_close($conn);
   
    foreach ($data->inputs as $in)
      if (array_key_exists("prev_out", $in))
        return check_tx_by_index($in->prev_out->tx_index, $srcaddr);
      else
        foreach ($data->out as $out)
          if ($out->addr==$srcaddr)
            return true;
    return false;
  }

  // bomb out if address isn't given
  $addr=$_GET["addr"];
  if ($addr=="")
  {
    header("HTTP/1.1 400 Bad Request");
    echo "<html><body><h1>400 Bad Request</h1></body></html>";
    exit;
  }
 
  // leaving source address empty searches for generation transactions
  $src_addr=$_GET["src"];

  // get transaction data
  $conn=curl_init("http://blockexplorer.com/q/mytransactions/".$addr);
  curl_setopt($conn, CURLOPT_RETURNTRANSFER, true);
  $data=json_decode(curl_exec($conn));
  curl_close($conn);  

  // filter out transactions for which we were paid
  foreach ($data as $tx)
  {
    if ($src_addr=="")
    {
      if ($tx->vin_sz==1)
        if ($tx->in[0]->prev_out->hash=="0000000000000000000000000000000000000000000000000000000000000000")
          foreach ($tx->out as $out)
            if ($out->address==$addr)
              $in[strtotime($tx->time)]=$out->value;
    }
    else
    {
      if ($_GET["simple"]!="")
      {
        foreach ($tx->out as $out)
          if ($out->address==$addr)
            foreach ($tx->out as $out)
              if ($out->address==$addr)
                $in[strtotime($tx->time)]=$out->value;
      }
      else
      {
        foreach ($tx->out as $out)
          if ($out->address==$addr)
            foreach ($tx->in as $input)
              if (check_tx_by_hash($input->prev_out->hash, $src_addr))
                foreach ($tx->out as $out)
                  if ($out->address==$addr)
                    $in[strtotime($tx->time)]=$out->value;
      }
    }
  }
 
  // get MtGox bid price
  $conn=curl_init("https://mtgox.com/api/0/data/ticker.php");
  curl_setopt($conn, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($conn, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.1 (KHTML, like Gecko) Chrome/21.0.1180.89 Safari/537.1");
  curl_setopt($conn, CURLOPT_RETURNTRANSFER, true);
  $mtgox_bid=json_decode(curl_exec($conn))->ticker->buy;
  curl_close($conn);  

  // calculate daily income data
  for ($i=strtotime(date("Y-m-d", min(array_keys($in)))); $i<time(); $i+=86400)
  {
    $sum=0.0;
    foreach (array_keys($in) as $time)
      if ($i<=$time && $time<$i+86400)
        $sum+=$in[$time];
    $chartdata[date("Y-m-d", $i)]=$sum;
  }
 
  // restrict it to the last 30 days
  while (count($chartdata)>30)
  {
    reset($chartdata);
    unset($chartdata[key($chartdata)]);
  }
 
  // build the graph
  $graph=new phpMyGraph();
  $cfg["title"]="Daily Income for $addr";
  $cfg["width"]=900;
  $cfg["height"]=600;
  ob_start();
  $graph->parseVerticalColumnGraph($chartdata, $cfg);
  $daily_graph_png=ob_get_contents();
  ob_end_clean();
 
  // calculate weekly income data
  unset($chartdata);
  for ($i=strtotime(date("Y-m-d", min(array_keys($in)))); $i<time(); $i+=86400*7)
  {
    $sum=0.0;
    foreach (array_keys($in) as $time)
      if ($i<=$time && $time<$i+86400*7)
        $sum+=$in[$time];
    $chartdata[date("Y-m-d", $i)]=$sum;
  }
 
  // restrict it to the last 30 weeks
  while (count($chartdata)>30)
  {
    reset($chartdata);
    unset($chartdata[key($chartdata)]);
  }
 
  // build the graph
  $graph=new phpMyGraph();
  $cfg["title"]="Weekly Income for $addr";
  $cfg["width"]=900;
  $cfg["height"]=600;
  ob_start();
  $graph->parseVerticalColumnGraph($chartdata, $cfg);
  $weekly_graph_png=ob_get_contents();
  ob_end_clean();
 
  // calculate total income
  $sum=0.0;
  foreach ($in as $amt)
    $sum+=$amt;
?>
<html>
<head><title>Generation Income for <?php echo $addr; ?></title></head>
<body>
<p><?php echo $sum." BTC (\$".number_format($sum*$mtgox_bid,2)." at MtGox bid price of \$".number_format($mtgox_bid,2)."/BTC)\n"; ?></p>
<?php echo '<img src="data:image/png;base64,'.base64_encode($daily_graph_png).'" />'; ?>
<br />
<?php echo '<img src="data:image/png;base64,'.base64_encode($weekly_graph_png).'" />'; ?>
</body>
</html>
  