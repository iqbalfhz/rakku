{{-- Sidebar accordion: hanya satu grup menu yang terbuka, diprioritaskan grup halaman aktif. --}}
<script>
    (() => {
        const storageKey = 'collapsedGroups'

        const groupElements = () => [
            ...document.querySelectorAll('#fi-main-sidebar .fi-sidebar-group.fi-collapsible[data-group-label]'),
        ].filter((group) => group.dataset.groupLabel)

        const collapsedExcept = (openLabel, collapsedGroups) => {
            const labels = groupElements().map((group) => group.dataset.groupLabel)

            return collapsedGroups
                .filter((label) => ! labels.includes(label))
                .concat(labels.filter((label) => label !== openLabel))
        }

        const storedCollapsedGroups = JSON.parse(localStorage.getItem(storageKey)) ?? []
        const activeLabel = document.querySelector('#fi-main-sidebar .fi-sidebar-group.fi-active')?.dataset.groupLabel
        const openLabel = activeLabel || groupElements()
            .map((group) => group.dataset.groupLabel)
            .find((label) => ! storedCollapsedGroups.includes(label))
        const collapsedGroups = collapsedExcept(openLabel, storedCollapsedGroups)

        localStorage.setItem(storageKey, JSON.stringify(collapsedGroups))

        groupElements().forEach((group) => {
            const isCollapsed = collapsedGroups.includes(group.dataset.groupLabel)
            const items = group.querySelector('.fi-sidebar-group-items')

            group.classList.toggle('fi-collapsed', isCollapsed)

            if (items) {
                items.style.display = isCollapsed ? 'none' : ''
            }
        })

        const makeSidebarAccordion = () => {
            const sidebar = window.Alpine.store('sidebar')
            const toggleCollapsedGroup = sidebar.toggleCollapsedGroup

            sidebar.toggleCollapsedGroup = function (label) {
                if (! this.groupIsCollapsed(label) || label.startsWith('sub_navigation_')) {
                    return toggleCollapsedGroup.call(this, label)
                }

                this.collapsedGroups = collapsedExcept(label, this.collapsedGroups)
            }
        }

        if (window.Alpine?.store('sidebar')) {
            makeSidebarAccordion()
        } else {
            document.addEventListener('alpine:initialized', makeSidebarAccordion, { once: true })
        }
    })()
</script>
