{{-- Master layout for the app shell and the sign-in pages. data-bs-theme is set from storage
     by the head script; ui.js manages data-sidebar-size by viewport width. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      data-layout="vertical"
      data-sidebar-visibility="show"
      data-topbar="light"
      data-sidebar="light"
      data-sidebar-size="lg"
      data-sidebar-image="none"
      data-preloader="disable">

@include('layouts.partials.head')

<body>
@yield('body')

@include('layouts.partials.scripts')
</body>
</html>
