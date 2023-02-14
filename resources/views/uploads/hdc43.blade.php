<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Upload 43</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
</head>

<body class="antialiased">

    <div class="row">

        <div class="col-6 bg-warning">
            <div class="container">
                <h5>Upload 43HDC</h5>
                <div class="mb-3">
                    <label for="exampleFormControlInput1" class="form-label">File</label>
                    <input type="file" class="form-control" name="fileUpload" required>
                </div>
                <div class="mb-3">
                    <label for="exampleFormControlTextarea1" class="form-label">Month</label>
                    <select name="month" id="" required>
                        <option value="">--Please Select--</option>
                        <option value="01">01</option>
                        <option value="02">02</option>
                        <option value="03">03</option>
                        <option value="04">04</option>
                        <option value="05">05</option>
                        <option value="06">06</option>
                        <option value="07">07</option>
                        <option value="08">08</option>
                        <option value="09">09</option>
                        <option value="10">10</option>
                        <option value="11">11</option>
                        <option value="12">12</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label for="exampleFormControlTextarea1" class="form-label">Year</label>
                    <select name="year" id="" required>
                        <option value="">--Please Select--</option>
                        <option value="2022">2565</option>
                        <option value="2023">2566</option>
                        <option value="2024">2567</option>
                    </select>
                </div>
            </div>
        </div>
    </div>



    <div class="row">
        <div class="container">
            <h5>Data HDC43</h5>
            <div class="col-12">

                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>cid</th>
                            <th>fname</th>
                            <th>lname</th>
                            <th>age</th>
                            <th>bd</th>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach ($hdc43s as $hdc43)
                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $hdc43->cid ?? '' }}</td>
                                <td>{{ $hdc43->fname ?? '' }}</td>
                                <td>{{ $hdc43->lname ?? '' }}</td>
                                <td>{{ $hdc43->age ?? '' }}</td>
                                <td>{{ $hdc43->bd ?? '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>

            </div>
        </div>


    </div>

</body>

<script>
    < script src = "https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js"
    integrity = "sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM"
    crossorigin = "anonymous" >
</script>
</script>

</html>
