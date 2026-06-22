<?php

include("../config/config.php");

$doctorId = $_GET['doctor'];

$stmt = $conn->prepare("
SELECT
SLOT_DATE,
SLOT_TIME,
STATUS
FROM SYARMIMI.DOCTOR_SLOT
WHERE ACCOUNT_ID = :doctor
ORDER BY SLOT_DATE,SLOT_TIME
");

$stmt->execute([
':doctor'=>$doctorId
]);

echo "<table class='table'>";

echo "
<tr>
<th>Date</th>
<th>Time</th>
<th>Status</th>
</tr>
";

while($row = $stmt->fetch(PDO::FETCH_ASSOC))
{
echo "
<tr>

<td>{$row['SLOT_DATE']}</td>

<td>{$row['SLOT_TIME']}</td>

<td>{$row['STATUS']}</td>

</tr>
";
}

echo "</table>";