<?php

require_once dirname(__DIR__) . '/app/config/bootstrap.php';

requireOwner();

$cafeId = currentCafeId();


/*
|--------------------------------------------------------------------------
| Café
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        name,
        slug
    FROM cafes
    WHERE id = :cafe_id
    LIMIT 1
");

$stmt->execute([
    'cafe_id' => $cafeId
]);

$cafe = $stmt->fetch();

if (!$cafe) {
    exit('Café not found.');
}


/*
|--------------------------------------------------------------------------
| QR Codes
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT
        id,
        table_number,
        code,
        destination_url,
        status,
        created_at
    FROM qr_codes
    WHERE cafe_id = :cafe_id
    ORDER BY table_number ASC
");

$stmt->execute([
    'cafe_id' => $cafeId
]);

$qrCodes = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>
    QR Codes - <?= e($cafe['name']) ?>
</title>


<!-- QRCode.js -->

<script
    src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"
></script>


<style>

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background: #f5f6f8;

    color: #222;
}

.container {
    max-width: 1200px;

    margin: auto;

    padding: 30px 20px;
}


/*
|--------------------------------------------------------------------------
| Topbar
|--------------------------------------------------------------------------
*/

.topbar {
    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 25px;
}

.topbar h1 {
    margin: 0;
}

.back {
    text-decoration: none;

    color: #333;

    font-weight: 600;
}


/*
|--------------------------------------------------------------------------
| Cards
|--------------------------------------------------------------------------
*/

.card {
    background: white;

    border-radius: 16px;

    padding: 25px;

    margin-bottom: 25px;

    box-shadow:
        0 5px 20px
        rgba(0, 0, 0, 0.06);
}


/*
|--------------------------------------------------------------------------
| Form
|--------------------------------------------------------------------------
*/

.form {
    display: flex;

    gap: 10px;

    flex-wrap: wrap;
}

input {
    padding: 12px 14px;

    border: 1px solid #ddd;

    border-radius: 8px;

    min-width: 220px;

    font-size: 15px;
}

input:focus {
    outline: none;

    border-color: #111827;
}


/*
|--------------------------------------------------------------------------
| Buttons
|--------------------------------------------------------------------------
*/

button {
    border: none;

    padding: 12px 20px;

    border-radius: 8px;

    background: #111827;

    color: white;

    cursor: pointer;

    font-size: 14px;
}

button:hover {
    opacity: .9;
}

button:disabled {
    opacity: .6;

    cursor: not-allowed;
}

.download-btn {
    margin-top: 10px;

    width: 100%;
}

.copy-btn {
    margin-top: 8px;

    width: 100%;
}


/*
|--------------------------------------------------------------------------
| Messages
|--------------------------------------------------------------------------
*/

.message {
    margin-top: 15px;

    font-weight: 600;
}

.success {
    color: #166534;
}

.error {
    color: #b91c1c;
}


/*
|--------------------------------------------------------------------------
| Table
|--------------------------------------------------------------------------
*/

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;

    border-collapse: collapse;

    min-width: 850px;
}

th,
td {
    padding: 14px;

    border-bottom:
        1px solid #eee;

    text-align: left;

    vertical-align: middle;
}

th {
    background: #f9fafb;

    font-size: 14px;
}


/*
|--------------------------------------------------------------------------
| Status
|--------------------------------------------------------------------------
*/

.badge {
    display: inline-block;

    padding: 5px 10px;

    border-radius: 20px;

    background: #dcfce7;

    color: #166534;

    font-size: 13px;

    font-weight: 600;
}


/*
|--------------------------------------------------------------------------
| URL
|--------------------------------------------------------------------------
*/

.url {
    max-width: 300px;

    word-break: break-all;

    font-size: 13px;

    color: #666;
}


/*
|--------------------------------------------------------------------------
| QR
|--------------------------------------------------------------------------
*/

.qr-box {
    width: 170px;

    text-align: center;
}

.qr-image {
    width: 150px;

    height: 150px;

    margin: auto;

    display: flex;

    align-items: center;

    justify-content: center;

    background: white;

    border-radius: 8px;
}

.qr-image img,
.qr-image canvas {
    max-width: 150px;

    max-height: 150px;
}


/*
|--------------------------------------------------------------------------
| Empty
|--------------------------------------------------------------------------
*/

.empty {
    text-align: center;

    padding: 40px 20px;

    color: #666;
}


/*
|--------------------------------------------------------------------------
| Responsive
|--------------------------------------------------------------------------
*/

@media (
    max-width: 700px
) {

    .container {
        padding: 20px 12px;
    }

    .topbar {
        align-items: flex-start;

        gap: 15px;

        flex-direction: column;
    }

    .card {
        padding: 18px;
    }

    .form {
        flex-direction: column;
    }

    input,
    .form button {
        width: 100%;
    }

}

</style>

</head>


<body>


<div class="container">


    <!-- ======================================================
         TOPBAR
    ======================================================= -->

    <div class="topbar">

        <h1>
            📱 QR Codes
        </h1>

        <a
            class="back"
            href="dashboard.php"
        >
            ← Dashboard
        </a>

    </div>


    <!-- ======================================================
         CREATE QR
    ======================================================= -->

    <div class="card">

        <h2>
            Create Table QR
        </h2>

        <p>
            Generate a QR code for a specific café table.
        </p>


        <form
            id="qrForm"
            class="form"
        >

            <?= csrfField() ?>


            <input
                type="text"
                name="table_number"
                placeholder="Example: 1"
                maxlength="30"
                autocomplete="off"
                required
            >


            <button
                type="submit"
                id="createBtn"
            >
                Create QR
            </button>

        </form>


        <div
            id="message"
            class="message"
        ></div>

    </div>


    <!-- ======================================================
         QR LIST
    ======================================================= -->

    <div class="card">

        <h2>
            Your QR Codes
        </h2>


        <?php if (!$qrCodes): ?>

            <div class="empty">

                <h3>
                    No QR codes yet
                </h3>

                <p>
                    Create your first table QR code above.
                </p>

            </div>

        <?php else: ?>


            <div class="table-wrapper">

                <table>

                    <thead>

                        <tr>

                            <th>
                                Table
                            </th>

                            <th>
                                QR Code
                            </th>

                            <th>
                                Code
                            </th>

                            <th>
                                Destination
                            </th>

                            <th>
                                Status
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach (
                        $qrCodes as $qr
                    ): ?>


                        <tr>


                            <!-- TABLE -->

                            <td>

                                <strong>
                                    Table
                                    <?= e(
                                        $qr['table_number']
                                    ) ?>
                                </strong>

                            </td>


                            <!-- QR -->

                            <td>

                                <div class="qr-box">

                                    <div
                                        id="qr-<?= (int) $qr['id'] ?>"
                                        class="qr-image"
                                    ></div>


                                    <button
                                        type="button"
                                        class="download-btn"
                                        onclick="downloadQR(
                                            <?= (int) $qr['id'] ?>,
                                            <?= json_encode(
                                                $qr['table_number']
                                            ) ?>
                                        )"
                                    >
                                        Download QR
                                    </button>


                                    <button
                                        type="button"
                                        class="copy-btn"
                                        onclick="copyURL(
                                            <?= json_encode(
                                                $qr['destination_url']
                                            ) ?>
                                        )"
                                    >
                                        Copy URL
                                    </button>

                                </div>

                            </td>


                            <!-- CODE -->

                            <td>

                                <strong>
                                    <?= e(
                                        $qr['code']
                                    ) ?>
                                </strong>

                            </td>


                            <!-- URL -->

                            <td>

                                <div class="url">

                                    <?= e(
                                        $qr['destination_url']
                                    ) ?>

                                </div>

                            </td>


                            <!-- STATUS -->

                            <td>

                                <span class="badge">

                                    <?= e(
                                        ucfirst(
                                            $qr['status']
                                        )
                                    ) ?>

                                </span>

                            </td>


                        </tr>


                    <?php endforeach; ?>


                    </tbody>

                </table>

            </div>


        <?php endif; ?>

    </div>


</div>


<script>


/*
|--------------------------------------------------------------------------
| Create QR
|--------------------------------------------------------------------------
*/

const form =
    document.getElementById(
        'qrForm'
    );


const button =
    document.getElementById(
        'createBtn'
    );


const message =
    document.getElementById(
        'message'
    );


form.addEventListener(
    'submit',
    async function(event) {

        event.preventDefault();


        button.disabled = true;


        message.className =
            'message';


        message.textContent =
            'Creating QR...';


        const formData =
            new FormData(form);


        try {

            const response =
                await fetch(
                    '../api/qr/create.php',
                    {
                        method: 'POST',

                        body: formData
                    }
                );


            const data =
                await response.json();


            if (!data.success) {

                message.className =
                    'message error';


                message.textContent =
                    data.message;


                button.disabled = false;


                return;
            }


            message.className =
                'message success';


            message.textContent =
                data.message +
                ' Code: ' +
                data.data.code;


            setTimeout(
                function() {

                    location.reload();

                },
                700
            );


        } catch (error) {

            console.error(
                'QR Error:',
                error
            );


            message.className =
                'message error';


            message.textContent =
                'Unable to create QR code.';


            button.disabled = false;

        }

    }
);


/*
|--------------------------------------------------------------------------
| Generate Existing QR Codes
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'DOMContentLoaded',
    function() {


        <?php foreach ($qrCodes as $qr): ?>


        const qrContainer =
            document.getElementById(
                'qr-<?= (int) $qr['id'] ?>'
            );


        if (
            qrContainer &&
            typeof QRCode !== 'undefined'
        ) {

            new QRCode(
                qrContainer,
                {
                    text:
                        <?= json_encode(
                            $qr['destination_url']
                        ) ?>,

                    width: 150,

                    height: 150,

                    correctLevel:
                        QRCode.CorrectLevel.H
                }
            );

        }


        <?php endforeach; ?>


    }
);


/*
|--------------------------------------------------------------------------
| Download QR
|--------------------------------------------------------------------------
*/

function downloadQR(
    id,
    tableNumber
) {

    const container =
        document.getElementById(
            'qr-' + id
        );


    if (!container) {

        alert(
            'QR container not found.'
        );

        return;
    }


    const canvas =
        container.querySelector(
            'canvas'
        );


    if (!canvas) {

        alert(
            'QR is not ready yet.'
        );

        return;
    }


    const link =
        document.createElement(
            'a'
        );


    link.download =
        'tablebuzz-table-' +
        tableNumber +
        '-qr.png';


    link.href =
        canvas.toDataURL(
            'image/png'
        );


    link.click();
}


/*
|--------------------------------------------------------------------------
| Copy URL
|--------------------------------------------------------------------------
*/

async function copyURL(
    url
) {

    try {

        await navigator.clipboard.writeText(
            url
        );


        alert(
            'QR URL copied!'
        );


    } catch (error) {

        console.error(
            error
        );


        alert(
            'Unable to copy URL.'
        );

    }

}

</script>


</body>

</html>