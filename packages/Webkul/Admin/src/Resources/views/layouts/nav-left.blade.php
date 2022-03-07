<div class="navbar-left">
    <ul class="menubar">
        @foreach ($menu->items as $menuItem)
        @if(trans($menuItem['name'])!="Velocity" &&  trans($menuItem['name'])!="Marketing" && trans($menuItem['name'])!="CMS")
            <li class="menu-item {{ $menu->getActive($menuItem) }}">
                <a href="{{ count($menuItem['children']) ? current($menuItem['children'])['url'] : $menuItem['url'] }}">
                    <span class="icon {{ $menuItem['icon-class'] }}"></span>
                    
                    <span>{{ trans($menuItem['name']) }}</span>
                </a>
            </li>
        @endif
        @endforeach
    </ul>
</div>