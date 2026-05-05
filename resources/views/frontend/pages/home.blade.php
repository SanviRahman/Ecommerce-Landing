@extends('frontend.layouts.master')

@section('title', 'Home Page')

@section('meta_description', 'This is the home page.')

@section('content')

<section class="py-5 bg-light">
    <div class="container">
        <div class="text-center">
            <h1 class="font-weight-bold">
                Welcome to Our Website
            </h1>

            <p class="lead text-muted">
                This page will automatically load active tracking pixel scripts from database.
            </p>

            <a href="#" class="btn btn-primary px-4">
                Shop Now
            </a>
        </div>
    </div>
</section>

@endsection