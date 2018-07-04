<?php
include_once('data_cfg.php');
// Example contents: 
// $host = "localhost";
// $user = "user";
// $pass = "pass";
// $db = "db";
// 
// $key = "google drive api key";

if (isset($test) && $test) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

$mysqli = new mysqli($host, $user, $pass, $db);

function create() {
    global $key, $mysqli;
    if (isset($_GET['id'])) {
        $id = $mysqli->real_escape_string($_GET['id']);
        if ($result = $mysqli->query("SELECT * FROM skits WHERE id='" . $id . "'")) {
            if ($row = $result->fetch_assoc()) {
                if ($is_skit = (isset($row['doc_id']) && $row['doc_id'])) {
                    $last_modified = strtotime($row['last_modified']);
                    $doc_id = $row['doc_id'];
                    $content = $row['content'];
                    $characters = $row['characters'];
                }
                $name = $row['name'];
                $parent = $row['parent'];
                $result->close();

                if ($is_skit) {
                    $fetch_url = "https://www.googleapis.com/drive/v3/files/" . $doc_id . "?fields=modifiedTime&key=" . $key;
                    $data = file_get_contents($fetch_url);
                    $data = json_decode($data, true);
                    if (isset($data['modifiedTime'])) {
                        if ($last_modified - strtotime($data['modifiedTime']) < 0) {
                            $skit = update($id, $name, $doc_id, $parent, true);
                        }
                    }
                } elseif ($result = $mysqli->query("SELECT * FROM skits WHERE parent='" . $id . "'")) {
                        $children = array();
                        while ($row = $result->fetch_assoc()) {
                            if ($is_skit = (isset($row['doc_id']) && $row['doc_id'])) $s_type = 'skit';
                            else $s_type = 'folder';
                            $s_id = $row['id'];
                            $s_name = $row['name'];

                            $children[] = array(
                                'type' => $s_type,
                                'id' => $s_id,
                                'name' => $s_name,
                            );
                        }
                        $result->close();
                        $skit = array('type' => 'folder', 'id' => $id, 'name' => $name, 'parent' => $parent, 'children' => $children);
                }


                if (!isset($skit) || !$skit) {
                    if (!$is_skit) {
                        return "Err";
                    }
                    $skit = array('type' => 'skit', 'id' => $id, 'name' => $name, 'parent' => $parent, 'doc_id' => $doc_id, 'content' => $content, 'characters' => $characters);
                }
                return $skit;
            }
        }
    } elseif (isset($_POST['id'])) {
        if (isset($_POST['delete']) && $_POST['delete'] === 'true') {
            $to_delete = deleteAll($mysqli->real_escape_string($_POST['id']));
            $mysqli->query("DELETE FROM skits WHERE id IN ('" . implode($to_delete, "', '") . "');");
            return "Err"; // This page shouldn't be displayed
        }
        $id = $mysqli->real_escape_string($_POST['id']);
        if (isset($_POST['name'])) { // Create new
            $name = $mysqli->real_escape_string($_POST['name']);
            if (isset($_POST['url'])) { // Create skit
                $url = $_POST['url'];
                $parent = "";
                if (isset($_POST['parent'])) $parent = $mysqli->real_escape_string($_POST['parent']);
                $skit = update($id, $name, $url, $parent);
                if ($skit) {
                    header('Location: ./?id=' . $id);
                    return $skit;
                }
            } else { // Create folder
                $query = "INSERT INTO skits (id, name) VALUES ('" . $id . "', '" . $name . "');";
                if (isset($_POST['parent'])) {
                    $parent = $mysqli->real_escape_string($_POST['parent']);
                    $query = "INSERT INTO skits (id, name, parent) VALUES ('" . $id . "', '" . $name . "', '" . $parent . "');";
                }
                if ($mysqli->query($query)) {
                    $res = array('id' => $id, 'name' => $name);
                    if (isset($parent)) {
                        $res['parent'] = $parent;
                    }
                    header('Location: ./?id=' . $id);
                    return $res;
                }

            }
        }
    } else {
        return "Create";
    }
    return "Err";
}

function update($id, $name, $url, $parent, $check_exists = true) {
    global $key, $mysqli;

    $doc_id = str_replace("https://docs.google.com/document/d/", "", preg_replace("/\/edit.*/", "", $url));
    $fetch_url = "https://www.googleapis.com/drive/v3/files/" . $doc_id . "/export?mimeType=text%2Fplain&key=" . $key;

    $content = file_get_contents($fetch_url);
    $content = preg_replace("/^[\s\S]*?===\s*/", "", $content);
    $content = preg_replace("/\s*===[\s\S]*$/", "", $content);

    $content_before = $content;
    $content = preg_replace("/\n *\n/m", "\n", $content);
    while ($content_before !== $content) {
        $content_before = $content;
        $content = preg_replace("/\n *\n/m", "\n", $content);
    }
    $content = preg_replace("/\n$/", "", $content);

    $content = preg_replace("/^\((.+)\)$/m", "$1", $content);
    $content = preg_replace("/ \((.+)\):/m", ": ($1)", $content);
    $content = preg_replace("/^/m", "<div class=\"line\">", $content);
    $content = preg_replace("/$/m", "</div>", $content);
    $content = preg_replace("/\"line\">([^:\n]+): /m", "\"line $1\">", $content);
    $content = preg_replace("/\(([^()]+)\)/m", "<span class=\"stage\">$1</span>", $content);

    $characters = ""; 
    $blacklist = "\"|stage|scene";
    while (preg_match("/\"line (((?!" . $blacklist . ").)*)\"/m", $content, $matches)) {
        $display_name = $matches[1];
        $internal = preg_replace("/\s+/", "-", strtolower($display_name));
        $content = str_replace($matches[0], "\"line " . $internal . "\"", $content);
        $blacklist .= "|" . $internal;
        $characters .= $internal . ":" . $display_name . ";";
    }

    $content = str_replace("\"line\">Scene", "\"line scene\">Scene", $content);
    $content = str_replace("\"line\"", "\"line stage\"", $content);
    $content = str_replace("…", "...", $content);
    $content = $mysqli->real_escape_string($content);

    $query = "UPDATE skits SET content='" . $content . "', characters='" . $characters . "', last_modified=NOW() WHERE id='" . $id . "';";
    if ($check_exists) {
        if (!(($result = $mysqli->query("SELECT * FROM skits WHERE id='" . $id . "'")) && $result->fetch_row())) {
            $query = "INSERT INTO skits (id, name, doc_id, content, characters) VALUES ('" . $id . "', '" . $name . "', '" . $doc_id . "', '" . $content . "', '" . $characters . "');";
            if ($parent) {
                $query = "INSERT INTO skits (id, name, parent, doc_id, content, characters) VALUES ('" . $id . "', '" . $name . "', '" . $parent . "', '" . $doc_id . "', '" . $content . "', '" . $characters . "');";
            }
        }
    }
    if ($result = $mysqli->query($query)) {
        return array('type' => 'skit', 'id' => $id, 'name' => $name, 'parent' => $parent, 'doc_id' => $doc_id, 'content' => $content, 'characters' => $characters);
    }
    return NULL;
}

function deleteAll($id) {
    global $mysqli;
    $to_delete = array();
    if ($result = $mysqli->query("SELECT doc_id FROM skits WHERE id='" . $id . "';")){
        if ($row = $result->fetch_assoc()) {
            if (!$row['doc_id'] && $result2 = $mysqli->query("SELECT id FROM skits WHERE parent='" . $id . "';")) {
                // Delete folder and all children
                while ($row2 = $result2->fetch_assoc()) {
                    $to_delete = array_merge($to_delete, deleteAll($row2['id']));
                }
            }
            $to_delete[] = $id;
        }
    }
    return $to_delete;
}

//var_dump(deleteAll('asdf'));
//exit();

$skit = create();
if ($skit === "Err") {
    $title = "Skits";
} elseif ($skit === "Create") {
    $title = "New Skit";
} else {
    $title = $skit['name'];
    if ($skit['parent']) {
        $title .= " | " . $skit['parent'];
    }
}

$mysqli->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="HandheldFriendly" content="true">

    <link href="favicon.png" rel="shortcut icon">
    <title><?php echo $title; ?></title>
</head>
<body>
    <?php if (isset($test) && $test): ?>
        <script type="text/javascript">console.log('<?php echo json_encode($skit); ?>');</script>
    <?php endif; ?>
    <div id="content">
        <?php if ($skit === "Err"): ?>
            <h1>Error. Make sure the id is correct.</h1>
        <?php elseif ($skit === "Create"): ?>
            <h1>Create New Skit</h1>
            <form action="./" method="post">
                <label for="id">Internal name (id): </label>
                <input type="text" name="id"><br>
                <label for="name">Display name: </label>
                <input type="text" name="name"><br>
                <label for="url">Google docs URL (Put === right before and after the skit lines. Leave blank to create a folder): </label><br>
                <input type="text" name="url" style="width: 100%"><br>
                <label for="parent">Parent (optional): </label>
                <?php if (isset($_GET['parent'])): ?>
                    <input type="text" name="parent" value="<?php echo $_GET['parent']; ?>"><br>
                <?php else: ?>
                    <input type="text" name="parent"><br>
                <?php endif; ?>
                <input type="submit" value="Submit">
            </form>
        <?php elseif ($skit['type'] === "folder"): ?>
            <h1 id="title">
                <?php
                echo $skit['name'];
                if ($skit['parent']) {
                    echo ' <a href="./?id=' . $skit['parent'] . '" aria-label="Go to parent folder"><i class="fa fa-arrow-up" aria-hidden="true" alt="Up arrow"></i></a>';
                }
                ?>
            </h1>
            <ul id="children">
                <?php
                foreach ($skit['children'] as $child) {
                    $name = $child['name'];
                    if ($child['type'] === "folder") {
                        $name = '<i class="fa fa-folder" aria-hidden="true" alt="Folder"></i> ' . $name;
                    }
                    echo '<li class="child"><a href="./?id=' . $child['id'] . '">' . $name . '</a> <a href="#" class="delete" data-type="' . $child['type'] . '" id="' . $child['id'] . '">[X]</a></li>';
                }
                ?>
                <a class="create" href="./?parent=<?php echo $skit['id']; ?>">[New Skit]</a>
            </ul>
        <?php else: ?>
            <h1 id="title">
                <?php
                echo $skit['name'];
                echo ' <a class="edit" href="https://docs.google.com/document/d/' . $skit['doc_id'] . '/edit" target="_blank" rel="noopener">[Edit]</a>';
                if ($skit['parent']) {
                    echo ' <a href="./?id=' . $skit['parent'] . '" aria-label="Go to parent folder"><i class="fa fa-arrow-up" aria-hidden="true" alt="Up arrow"></i></a>';
                }
                ?>
            </h1>
            <div id="controlbox">
                <label for="char">Character: </label>
                <select id="char" name="char">
                    <option value="all" selected="selected">All</option>
                    <?php
                    $characters = explode(';', $skit['characters']);
                    foreach ($characters as $character) {
                        if (strpos($character, ':')) {
                            $data = explode(':', $character);
                            echo '<option value="' . $data[0] . '">' . $data[1] . '</option>';
                        }
                    }
                    ?>
                </select><br />
            </div>
            <div id="skitlines">
                <?php echo html_entity_decode($skit['content']); ?>
            </div>
        <?php endif; ?>
    </div>

    <!--    At end to speed up page load  -->
    <script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
    <?php if (isset($skit['type']) && $skit['type'] === 'skit'): ?>
        <script type="text/javascript" src="assets/js/skits.js?ver=20180704r0"></script>
        <script type="text/javascript">
            var id = "<?php echo $skit['id'] ?>";
        </script>
    <?php endif; ?>

    <link href='https://fonts.googleapis.com/css?family=Lato:300,400,700,300italic,400italic' rel='stylesheet' type='text/css'>
    <link href='https://fonts.googleapis.com/css?family=Raleway:400,300,700' rel='stylesheet' type='text/css'>
    <link type="text/css" rel="stylesheet" href="assets/css/font-awesome.min.css?ver=20180704r0">

    <link type="text/css" rel="stylesheet" href="assets/css/style.min.css?ver=20180704r3">
    <link type="text/css" rel="stylesheet" href="assets/css/skits.min.css?ver=20180704r0">
    <?php if (isset($skit['type']) && $skit['type'] === 'folder'): ?>
        <script type="text/javascript">
            $('a.delete').click(function(event) {
                var deleteId = event.target.id;
                var afterText = '?';
                if ($(event.target).data("type") === "folder") afterText = ' and all its contents?';
                if (confirm('Are you sure you want to delete ' + deleteId + afterText)) {
                    $.post('./', {id: deleteId, delete: 'true'}, function() {
                        window.location.reload(true);
                    });
                }
            });
        </script>
    <?php endif; ?>
</body>
</html>
