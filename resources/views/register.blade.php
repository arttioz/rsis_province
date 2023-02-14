@extends('app')
@section('content')
<main class="login-form">
    <div class="cotainer">
        <div class="row justify-content-center">
            <div class="col-md-4">
                <div class="card">
                    <h3 class="card-header text-center">Login</h3>
                    <div class="card-body">
                        <form method="POST" action="{{ route('login.custom') }}">
                            @csrf
                            <div class="form-group mb-3">
                                <input type="text" placeholder="name" id="name" class="form-control" name="name" required
                                    autofocus>
                                @if ($errors->has('name'))
                                <span class="text-danger">{{ $errors->first('name') }}</span>
                                @endif
                            </div>
                            <div class="form-group mb-3">
                                <input type="text" placeholder="phone" id="phone" class="form-control" name="phone" required>
                                @if ($errors->has('phone'))
                                <span class="text-danger">{{ $errors->first('phone') }}</span>
                                @endif
                            </div>
                            <div class="form-group mb-3">
                                <input type="text" placeholder="email" id="email" class="form-control" name="email" required>
                                @if ($errors->has('email'))
                                <span class="text-danger">{{ $errors->first('email') }}</span>
                                @endif
                            </div>
                            <div class="form-group mb-3">
                                <input type="text" placeholder="confirm_email" id="confirm_email" class="form-control" name="confirm_email" required>
                                @if ($errors->has('confirm_email'))
                                <span class="text-danger">{{ $errors->first('confirm_email') }}</span>
                                @endif
                            </div>
                            <div class="form-group mb-3">
                                <input type="file" id="cid" class="form-control" name="cid" required>
                                @if ($errors->has('cid'))
                                <span class="text-danger">{{ $errors->first('cid') }}</span>
                                @endif
                            </div>
                            <div class="form-group mb-3">
                                <input type="text" placeholder="organize" id="organize" class="form-control" name="organize" required>
                                @if ($errors->has('organize'))
                                <span class="text-danger">{{ $errors->first('organize') }}</span>
                                @endif
                            </div>
                            <div class="form-group mb-3">
                                <input type="file" id="pic" class="form-control" name="pic" required>
                                @if ($errors->has('picture'))
                                <span class="text-danger">{{ $errors->first('picture') }}</span>
                                @endif
                            </div>
                            <div class="d-grid mx-auto">
                                <button type="submit" class="btn btn-dark btn-block">Register</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>
@endsection




