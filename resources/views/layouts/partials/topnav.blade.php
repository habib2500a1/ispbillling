<nav class="navbar navbar-light navbar-glass navbar-top navbar-expand-lg" style="display: none;">
    <button class="btn navbar-toggler-humburger-icon navbar-toggler me-1 me-sm-3" type="button" data-bs-toggle="collapse" data-bs-target="#navbarVerticalCollapse" aria-controls="navbarVerticalCollapse" aria-expanded="false" aria-label="Toggle Navigation">
        <span class="navbar-toggle-icon">
            <span class="toggle-line"></span>
        </span>
    </button>
    @include('layouts.partials.navbar-brand-mark', ['class' => 'me-1 me-sm-3', 'logoStyle' => 'width: auto; height: auto; max-height: 45px; max-width: 160px; object-fit: contain;'])
    <div class="collapse navbar-collapse scrollbar" id="navbarStandard">
        @include('layouts.partials.searchbar')
    </div>
    @include('layouts.partials.userpanel')
</nav>

<nav class="navbar navbar-light navbar-glass navbar-top navbar-expand-lg" style="display: none;" data-move-target="#navbarVerticalNav" data-navbar-top="combo">
    <button class="btn navbar-toggler-humburger-icon navbar-toggler me-1 me-sm-3" type="button" data-bs-toggle="collapse" data-bs-target="#navbarVerticalCollapse" aria-controls="navbarVerticalCollapse" aria-expanded="false" aria-label="Toggle Navigation">
        <span class="navbar-toggle-icon">
            <span class="toggle-line"></span>
        </span>
    </button>
    @include('layouts.partials.navbar-brand-mark', ['class' => 'me-1 me-sm-3', 'logoStyle' => 'width: auto; height: auto; max-height: 45px; max-width: 160px; object-fit: contain;'])
    @include('layouts.partials.navpanel')
    @include('layouts.partials.userpanel')
</nav>
