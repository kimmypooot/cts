<?php
// Include PHPMailer
require 'phpmailer/PHPMailer.php';
require 'phpmailer/SMTP.php';
require 'phpmailer/Exception.php';

// Database connection
include 'connect.php';

try {
    // Create zip archive
    $zip = new ZipArchive();
    $zipFilename = 'db_backup/IMIS-backup_db-' . date('m-d-Y') . '.zip';
    if ($zip->open($zipFilename, ZipArchive::CREATE) !== TRUE) {
        throw new Exception('Failed to create zip file');
    }

    // Get all tables in the database
    $tablesStmt = $conn->query("SHOW TABLES");
    $tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($tables as $table) {
        $sql = "-- Dumping table structure for `$table`\n\n";

        // Get CREATE TABLE statement
        $createStmt = $conn->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_ASSOC);
        $sql .= $createStmt['Create Table'] . ";\n\n";

        // Get table data
        $rowsStmt = $conn->query("SELECT * FROM `$table`");
        $rows = $rowsStmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($rows) > 0) {
            $sql .= "-- Dumping data for `$table`\n\n";

            // Prepare INSERT INTO statements in batches
            $columns = array_map(function($col){ return "`$col`"; }, array_keys($rows[0]));
            $columnsList = implode(", ", $columns);

            foreach ($rows as $row) {
                $values = array_map(function($value) use ($conn) {
                    if (is_null($value)) {
                        return "NULL";
                    }
                    // Escape single quotes and backslashes
                    return "'" . str_replace(["\\", "'"], ["\\\\", "\\'"], $value) . "'";
                }, $row);
                $valuesList = implode(", ", $values);

                $sql .= "INSERT INTO `$table` ($columnsList) VALUES ($valuesList);\n";
            }
            $sql .= "\n";
        } else {
            $sql .= "-- Table `$table` is empty\n\n";
        }

        // Add the SQL string to the zip as a .sql file
        $zip->addFromString("$table.sql", $sql);
    }

    $zip->close();

    // Setup email
    $mail = new PHPMailer\PHPMailer\PHPMailer();
    $mail->isSMTP();
    $mail->Host = 'smtp.gmail.com';
    $mail->SMTPAuth = true;
    $mail->Username = 'cscro8.eams@gmail.com';
    $mail->Password = 'dwurukfdihjhmpbn';
    $mail->SMTPSecure = 'tls';
    $mail->Port = 587;
    $mail->setFrom('cscro8.eams@gmail.com', 'CSC RO VIII - Integrated Management Information System');
    $mail->addAddress('kmbanoyo@csc.gov.ph', 'CSC RO VIII Website Administrator');
    $mail->addCC('kimbenedick.banoyo@gmail.com', 'CSC RO VIII Website Administrator');
    // $mail->addCC('rmestoque@csc.gov.ph', 'SrHRS Ryan Jose Estoque, ESD');

    $mail->addAttachment($zipFilename);

    $currentDate = date('m-d-Y');
    $mail->Subject = "CSC RO VIII - IMIS Database Backup as of $currentDate";
    $mail->Body = 'Attached is the backup file of the IMIS central database in SQL format with each table as a separate SQL file.';

    echo $mail->send() ? 'success' : 'error';

} catch (PDOException $e) {
    echo 'Database backup error: ' . $e->getMessage();
} catch (Exception $e) {
    echo 'Email sending error: ' . $e->getMessage();
}
?>
