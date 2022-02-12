<?php

function get_existing_relations() {
    global $link;
    $sql = "SELECT i.TABLE_NAME as 'Table Name', k.COLUMN_NAME as 'Foreign Key', 
            k.REFERENCED_TABLE_NAME as 'Primary Table', k.REFERENCED_COLUMN_NAME as 'Primary Key',
            i.CONSTRAINT_NAME as 'Constraint Name' 
            FROM information_schema.TABLE_CONSTRAINTS i
            LEFT JOIN information_schema.KEY_COLUMN_USAGE k ON i.CONSTRAINT_NAME = k.CONSTRAINT_NAME
            WHERE i.CONSTRAINT_TYPE = 'FOREIGN KEY' AND i.TABLE_SCHEMA = DATABASE()";
    $relations = [];
    $result = mysqli_query($link, $sql);
    if ($result && $result->num_rows > 0) {
	while ($row = mysqli_fetch_assoc($result)) {
            $relations[] = $row;
	}
    }
    return $relations;
}

if(isset($_POST['index'])) {
    
    if((isset($_POST['server'])) && $_POST['server'] <> '') {
        $server=trim($_POST['server']);
    } else {
        $server = "localhost";
    }
	if(isset($_POST['username'])) $username=trim($_POST['username']);
	if(isset($_POST['password'])) $password=trim($_POST['password']);
	if(isset($_POST['database'])) $database=trim($_POST['database']);
	if(isset($_POST['numrecordsperpage'])) $numrecordsperpage=$_POST['numrecordsperpage'];

	// Attempt to connect to MySQL database 
	$link = mysqli_connect($server, $username, $password, $database);
	// Check connection
	if($link === false)
		die("ERROR: Could not connect. " . mysqli_connect_error());

	// Clean up User inputs against SQL injection 
	foreach($_POST as $k => $v) {
		$_POST[$k] = mysqli_real_escape_string($link, $v);
	}

	if (!file_exists('app'))
		mkdir('app', 0777, true);


    $helpersfilename = 'helpers.php';
    $handle = fopen('helpers.php', "r") or die("Unable to open Helpers file!");;
    $helpers = fread($handle, filesize($helpersfilename));
    fclose($handle);

    $helpersfile = fopen("app/".$helpersfilename, "w") or die("Unable to create Helpers file!");
    fwrite($helpersfile, $helpers);
	fclose($helpersfile);

	$configfile = fopen("app/config.php", "w") or die("Unable to open Config file!");
	$txt  = "<?php \n";
	$txt .= "\$db_server = '$server'; \n";
	$txt .= "\$db_name = '$database'; \n";
	$txt .= "\$db_user = '$username'; \n";
	$txt .= "\$db_password = '$password'; \n";
	$txt .= "\$no_of_records_per_page = $numrecordsperpage; \n\n";
	$txt .= "\$link = mysqli_connect(\$db_server, \$db_user, \$db_password, \$db_name); \n";

    $txt .= '$query = "SHOW VARIABLES LIKE \'character_set_database\'";' ."\n";
    $txt .= 'if ($result = mysqli_query($link, $query)) {' ."\n";
    $txt .= '    while ($row = mysqli_fetch_row($result)) {' ."\n";
    $txt .= '        if (!$link->set_charset($row[1])) {' ."\n";
    $txt .= '            printf("Error loading character set $row[1]: %s\n", $link->error);' ."\n";
    $txt .= '            exit();' ."\n";
    $txt .= '        } else {' ."\n";
    $txt .= '            // printf("Current character set: %s", $link->character_set_name());' ."\n";
    $txt .= '        }' ."\n";
    $txt .= '    }' ."\n";
    $txt .= '}' ."\n";

	$txt .= "\n?>";
	fwrite($configfile, $txt);
	fclose($configfile);

}
require "app/config.php";

if(isset($_POST['submit'])){
    $tablename = $_POST['tablename'];
    $fkname = $_POST['fkname'];

    $sql = "ALTER TABLE $tablename DROP FOREIGN KEY $fkname";
    if ($result = mysqli_query($link, $sql)) {
        echo "The foreign_key '$fkname' was deleted from '$tablename'";
    } else {
        echo("Something went wrong. Error description: " . mysqli_error($link));
    }
}

if(isset($_POST['addkey'])){
    $from_table = $_POST['from_table'];
    $from_column = $_POST['from_column'];
    $to_table = $_POST['to_table'];
    $to_column = $_POST['to_column'];
    if (empty($from_table) || empty($from_column) || empty($to_table) || empty($to_column)) {
        echo "Error: Please ensure both source and destination tables and columns are selected";   
    } else {
        add_foreign_key($from_table, $from_column, $to_table, $to_column);
    }
}

function add_foreign_key($from_table, $from_column, $to_table, $to_column) {
    global $link;
    // Get existing relations to check for duplicate foreign key names
    $relations = get_existing_relations();
    $existing_constraint_names = [];
    foreach ($relations as $r) {
	    $existing_constraint_names[ $r[ 'Constraint Name' ] ] = true;
    }

    // The default constraint name is [fromtable]_fk_[totable]_1
    // If that already exists, increment the number until it doesn't
    $fk_name = $from_table . '_fk_' . $to_table;
    $fk_num = 1;
    while (isset($existing_constraint_names[$fk_name . '_' . $fk_num])) {
        $fk_num++;
    }
    $fk_name .= '_' . $fk_num;

    $ondel_val = $_POST['ondelete'];
    $onupd_val = $_POST['onupdate'];

    switch ($ondel_val) {
        case "cascade":
           $ondel = "ON DELETE CASCADE";
            break;
        case "setnull":
            $ondel = "ON DELETE SET NULL";
            break;
       case "restrict":
           $ondel = "ON DELETE RESTRICT";
           break;
       default:
           $ondel = "";
    }

    switch ($onupd_val) {
        case "cascade":
           $onupd = "ON UPDATE CASCADE";
            break;
        case "setnull":
            $onupd = "ON UPDATE SET NULL";
            break;
       case "restrict":
            $onupd = "ON UPDATE RESTRICT";
            break;
       default:
            $onupd = "";
    }

    $sql = "ALTER TABLE $from_table ADD FOREIGN KEY $fk_name ($from_column) REFERENCES $to_table($to_column) $ondel $onupd";
    if ($result = mysqli_query($link, $sql)) {
        echo "The foreign_key '$fk_name' was created from $from_table($from_column) to $to_table)($to_column)";
    } else {
        echo("Something went wrong. Error description: " . mysqli_error($link));
    }
}

?>
<!doctype html>
<html lang="en">
<head>
    <title>Select Relations</title>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css" integrity="sha384-9aIt2nRpC12Uk9gS9baDl411NQApFmC26EwAOH8WgZl5MYYxFfc+NcPb1dKGj7Sk" crossorigin="anonymous">
    <link rel="stylesheet" href="cruddiy.css">
</head>
<body>
<section class="py-5">
    <div class="container" style='max-width:75%'>
        <div class="row">
            <div class="col-md-12 mx-auto">
                <div class="text-center">
                    <h4 class="mb-0">Existing Table Relations</h4><br>
                    <?php
                    $relations = get_existing_relations();
                    if (! empty($relations)) {
                        ?>
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <?php
                                    foreach ($relations[0] as $col => $value) {
                                        echo "<th>";
                                        echo $col;
                                        echo "</th>"; 
                                    }
				    echo "<th>Delete</th>";
                                    ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                foreach ($relations as $row) {
                                    echo "<tr>";
                                    foreach ($row as $col => $value) {
                                        echo "<td>" . $value . "</td>";
                                    }
                                    echo "<td class='fk-delete'>";
                                    echo '<form method="post" action="' . htmlspecialchars($_SERVER['PHP_SELF']) . '">';
                                    echo '<input type="hidden" name="tablename" value="';
                                    echo htmlspecialchars($row['Table Name']) .'">';
                                    echo '<input type="hidden" name="fkname" value="';
                                    echo htmlspecialchars($row['Constraint Name']) . '">';
                                    echo "<button type='submit' id='singlebutton' name='submit' class='btn btn-danger'>Delete</button>"; 
                                    echo "</form></td>";
                                    echo "</tr>";
                                }
                                ?>
                            </tbody>
                        </table> 
                        <?php
			} else {
                            echo "</thead><tbody><tr><td>No relations found</td></tr>";
                        }
                        ?>
                </div>
                <div class="text-center">
                    <h4 class="mt-4 mb-2">Add New Table Relation</h4>
                    <form method="post" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                        <?php
		        $sql = "select TABLE_NAME from information_schema.TABLES where TABLE_SCHEMA = '$db_name' order by TABLE_NAME";
                        $result = mysqli_query($link,$sql);
                        $tables = [];
                        while (( $row = mysqli_fetch_array($result) )) {
                            $tables[] = $row[0];
                        }
                        ?>
                        <fieldset>
			    <legend>Foreign key (e.g. employee.company_id)</legend>
                            <label for='from_table'>Table</label>
                            <select name='from_table' id='from_table'>
                                <option value="">-- select table --</option>
                            <?php 
                            foreach ($tables as $table) {
                                echo '<option value="' . $table . '">' . $table . '</option>';
                            }
			    ?>
                            </select>
                            <label for='from_column'>Column</label>
                            <select name='from_column' id='from_column'>
			        <option>&lt;-- select table</option><?php /* Real values will be added by AJAX */ ?>
                            </select>
			</fieldset>
                        <fieldset>
			    <legend>is related to (e.g. company.id)</legend>
                            <label for='to_table'>Table</label>
                            <select name='to_table' id='to_table'>
                                <option value="">-- select table --</option>
                            <?php 
                            foreach ($tables as $table) {
                                echo '<option value="' . $table . '">' . $table . '</option>';
                            }
			    ?>
                            </select>
                            <label for='to_column'>Column</label>
                            <select name='to_column' id='to_column'>
			        <option>&lt;-- select table</option><?php /* Real values will be added by AJAX */ ?>
                            </select>
			</fieldset>
   
                        <fieldset>
			    <legend>Update and Delete handling (optional)</legend>
                            <label for="onupdate">On Update</label>
                            <select name='onupdate' id='onupdate'>";
                                <option value="">Choose action (optional)</option>
                                <option value="restrict">On Update: Restrict</option>
                                <option value="cascade">On Update: Cascade</option>
                                <option value="setnull">On Update: Set Null</option>
                            </select>
                            <br>
                            <label for="ondelete">On Delete</label>
                            <select name='ondelete' id='ondelete'>";
                                <option value="">Choose action (optional)</option>
                                <option value="restrict">On Delete: Restrict</option>
                                <option value="cascade">On Delete: Cascade</option>
                                <option value="setnull">On Delete: Set Null</option>
                            </select>
                        </fieldset>
                        <button type="submit" id="singlebutton" name="addkey" class="btn btn-primary">Create relation</button>
                    </form>
                </div>
            </div<>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12 mx-auto mt-2">
            <hr>
            <p>
	        On this page you can add new or delete existing table relations i.e. foreign keys. Having foreign keys will result in Cruddiy forms with cascading deletes/updates and dropdown fields populated by foreign keys. If it is not clear what you want or need to do here, it is SAFER to skip this step and move to the next step! You can always come back later and regenerate new forms.
            </p>
            <hr>
        </div>
        <div class="col-md-12 mx-auto mt-2">
            <form method="post" action="tables.php">
                <button type="submit" id="singlebutton" name="singlebutton" class="btn btn-success">Continue CRUD Creation Process</button>
            </form>     
        </div>
    </div>
</section>

<script src="https://code.jquery.com/jquery-3.5.1.min.js" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js" integrity="sha384-OgVRvuATP1z7JjHLkuOU7Xw704+h835Lr+6QL9UvYjZE3Ipu6Tp75j7Bh/kR0JKI" crossorigin="anonymous"></script>

<script type="text/javascript">
    (function($) {
        $(document).ready(function() {
            let get_columns = function(tablename, $select_to_populate ) {
	        $.ajax({
                    'url': 'ajax_columns.php',
	            'data': {
		        'table': tablename,
                    },
                    'method': 'get',
		    'success': function(data) {
			    // Remove any existing options
			    $select_to_populate.html('');
			    for (let i = 0; i < data.length; i++) {
				let $opt = $('<option/>').appendTo($select_to_populate);
				$opt.attr('value', data[i]);
				$opt.html(data[i]);
			    }
		    },
                });
            };
	    $('#from_table').change(function() {
		    let tablename = $(this).val();
		    get_columns(tablename, $('#from_column'));
            });
	    $('#to_table').change(function() {
		    let tablename = $(this).val();
		    get_columns(tablename, $('#to_column'));
	    });
        });
    })(jQuery);
</script>

</body>
</html>

