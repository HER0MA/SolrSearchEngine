<?php
ini_set('memory_limit','1024M');
include 'SpellCorrector.php';
include 'simple_html_dom.php';
header('Content-Type: text/html; charset=utf-8');
$div=false;
$correct = "";
$check= "";
$output = "";
$limit = 10;
$query = isset($_REQUEST['q']) ? $_REQUEST['q'] : false;
$results = false;
$urlMap = array_map('str_getcsv', file('./map.csv'));

if ($query) {
  $choice = isset($_REQUEST['sort'])? $_REQUEST['sort'] : "default";
  require_once('solr-php-client/Apache/Solr/Service.php');
  $solr = new Apache_Solr_Service('localhost', 8983, '/solr/myexample/');

  if (get_magic_quotes_gpc() == 1) {
    $query = stripslashes($query);
  }

  try {
    if ($choice == "default")
      $parameter = array('sort' => '');
    else {
      $parameter = array('sort' => 'pageRankFile desc');
    }
    $word = explode(" ",$query);
    $spell = $word[sizeof($word)-1];
    for ($i = 0; $i < sizeOf($word); $i++) {
      ini_set('memory_limit',-1);
      ini_set('max_execution_time', 300);
      $correction = SpellCorrector::correct($word[$i]);
      if ($correct != "")
        $correct = $correct."+".trim($correction);
      else {
        $correct = trim($correction);
      }
        $check = $check." ".trim($correction);
    }
    $check = str_replace("+"," ",$correct);
    $div = false;
    if (strtolower($query) == strtolower($check)) {
      $results = $solr->search($query, 0, $limit, $parameter);
    } else {
      $div =true;
      $results = $solr->search($query, 0, $limit, $parameter);
      $url = "http://localhost/index.php?q=$correct&sort=$choice";
      $output = "Did you mean: <a href='$url'>$check</a>";
    }

  } catch (Exception $e) {
    die("<html><head><title>SEARCH EXCEPTION</title><body><pre>{$e->__toString()}</pre></body></html>");
  }
}

?>
<html>
  <head>
    <title>Search Engine</title>
    <link rel="stylesheet" href="http://code.jquery.com/ui/1.11.4/themes/smoothness/jquery-ui.css">
    <script src="http://code.jquery.com/jquery-1.10.2.js"></script>
    <script src="http://code.jquery.com/ui/1.11.4/jquery-ui.js"></script>
  </head>
  <body>
    <style>
      h1 {
        text-align: center;
      }

      form {
        text-align: center;
      }
    </style>

    <h1>Search Engine</h1>

    <form  accept-charset="utf-8" method="get">
      <input id="q" name="q" type="text" value="<?= htmlspecialchars($query, ENT_QUOTES, 'utf-8'); ?>" style="border:1px solid black;padding:0px;height:25px;width:300px;font-size:15px;"/>
      <input type="image" src="search.png" name="submit" border="0" alt="submit" height="25px" width="25px"/>
      <br><br>
      Select ranking algorithm: <br><br>
      <input type="radio" name="sort" value="default" checked <?php if(isset($_REQUEST['sort']) && $choice == "default") { echo 'checked="checked"';} ?>>Solr Default
      <input type="radio" name="sort" value="pagerank" <?php if(isset($_REQUEST['sort']) && $choice == "pagerank") { echo 'checked="checked"';} ?>>PageRank
    </form>

<script>
$(function() {
  let URL_PREFIX = "http://localhost:8983/solr/myexample/suggest?q=";
  let URL_SUFFIX = "&wt=json&indent=true";
  let count=0;

  $("#q").autocomplete({
    source : function(request, response) {
      let correct = "", before = "";
      let query = $("#q").val().toLowerCase();
      let character_count = query.length - (query.match(/ /g) || []).length;
      let space =  query.lastIndexOf(' ');
      if (query.length - 1 > space && space != -1){
        correct = query.substr(space+1);
        before = query.substr(0,space);
      } else {
        correct = query.substr(0); 
      }
      let URL = URL_PREFIX + correct + URL_SUFFIX;
      console.log(URL);
        $.ajax({
        url : URL,
        success : function(data) {
          let tmp = data.suggest.suggest;

          console.log(tmp, correct);
          let tags = tmp[correct]['suggestions'];
          let results = [];
          for (let i = 0; i < tags.length; i++) {
            if (before === "") {
              results.push(tags[i]['term']);
            } else {
              results.push(before + " " + tags[i]['term']);
            }
          }
          response(results);
        },
        dataType : 'jsonp',
        jsonp : 'json.wrf'
      });
    },
    minLength : 1
  })
});
</script>

<?php
if ($div) {
  echo $output;
}
$count = 0;
$pre = "";

if ($results) {
  $total = (int) $results->response->numFound;
  $start = min(1, $total);
  $end = min($limit, $total);
?>
    <div>Results <?= $start; ?> - <?= $end;?> of <?= $total; ?>:</div>
    <ol>

<?php
foreach ($results->response->docs as $doc) {
  if (is_array($doc->title)) {
    $title = $doc->title[0];
  } else {
    $title = $doc->title;
  }

  $id = $doc->id;
  $or_id = $id;

  $key = str_replace("~/solr/crawl_data", "", $id); 

  $description = $doc->og_description;
  $url = "";
  if (isset($doc->og_url)) {
    if (is_array($doc->og_url)) {
      $url = $doc->og_url[0];
    } else {
      $url = $doc->og_url;
    }
  } else {
    foreach ($urlMap as $mapping) 
    {
      if ($mapping[0] === $key)
      {
        $url = $mapping[1];
      }
    }
  }
  $url = $url == "" ? "https://www.reuters.com/" : $url;

  $searchterm = $_GET["q"];
  $ar = explode(" ", $searchterm);
  $filename = $id;
  $html = file_get_html($filename)->plaintext;
  $sentences = explode(".", $html);
  $words = explode(" ", $query);
  $snippet = "";
  $text = "/";
  $start_delim = "(?=.*?\b";
  $end_delim = ".*?)";

  foreach ($words as $item) {
    $text = $text.$start_delim.$item.$end_delim;
  }
  $text = $text."^.*$/i";
  foreach ($sentences as $sentence) {
    $sentence = strip_tags($sentence);

    if (preg_match($text, $sentence)) {
      $snippet = $snippet.$sentence;
      $temp_snippet = strtolower($snippet);

      if (strlen($snippet) > 160) {
        $index = -1;
        $word = "";
        foreach ($words as $item) {
          $index = strpos($temp_snippet, strtolower($item));
          if ($index != false) {
            $word = $item;
            break;
          }
        }
        if ($index == -1) {
          break;
        }

        $left_len = $index > 75 ? 75 : $index;
        $right_len = strlen($snippet) - $index > 75 ? 75 : strlen($snippet) - $index;
        $q_len = strlen($item);

        $snippet = substr($snippet, $index-$left_len, $left_len)." <b>".$item."</b> ".substr($snippet, $index+$q_len, $right_len-$q_len);
      }
      foreach ($words as $single_query) {
        $snippet = str_replace($single_query,"<b>".$single_query."</b>", $snippet);
        $snippet = str_replace(ucfirst($single_query),"<b>".ucfirst($single_query)."</b>", $snippet);
        $snippet = str_replace(strtoupper($single_query),"<b>".strtoupper($single_query)."</b>", $snippet);
      }
      break;
    } 
    if ($snippet == ""){
      $cur_query = "";
      if (count($words) > 1) {
        foreach($words as $item) {
          $cur_query = $item;
          $singletext = "/";
          $singletext = $singletext.$start_delim.$item.$end_delim."^.*$/i";
          if (preg_match($singletext, $sentence)) {
            $snippet = $sentence;
            break;
          }
        }
      }
      if ($snippet != "") {
        if (strlen($snippet) > 160) {
          $temp_snippet = strtolower($snippet);

          $index = -1;
          $word = "";
          foreach ($words as $item) {
            $index = strpos($temp_snippet, strtolower($item));
            if ($index != false) {
              $word = $item;
              break;
            }
          }
          if ($index == -1) {
            break;
          }

          $left_len = $index > 75 ? 75 : $index;
          $right_len = strlen($snippet) - $index > 75 ? 75 : strlen($snippet) - $index;
          $q_len = strlen($item);

          $snippet = substr($snippet, $index-$left_len, $left_len)." <b>".$item."</b> ".substr($snippet, $index+$q_len, $right_len-$q_len);
        }

        foreach($words as $single_query) {
          $snippet = str_replace($single_query,"<b>".$single_query."</b>", $snippet);
          $snippet = str_replace(ucfirst($single_query),"<b>".ucfirst($single_query)."</b>", $snippet);
          $snippet = str_replace(strtoupper($single_query),"<b>".strtoupper($single_query)."</b>", $snippet);
        }
        
        break;
      }
    }
  }
  if($snippet == ""){
    $snippet = "No snippet found";
  }
?>

    <li>
      <a href="<?= $url; ?>"><b><?= htmlspecialchars($title, ENT_NOQUOTES, 'utf-8'); ?></b></a><br>
      <i><a href="<?= $url; ?>"><?= $url; ?></a></i><br>
      <?= htmlspecialchars($id, ENT_NOQUOTES, 'utf-8'); ?><br>
      <?php if ($snippet == "No snippet found") {
        echo htmlspecialchars($snippet, ENT_NOQUOTES, 'utf-8');
      } else {
        echo "...".$snippet."...";
      }?>
    </li><br>

<?php } ?>
</ol>
<?php } ?>
  </body>
</html>