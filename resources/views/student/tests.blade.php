<x-layouts.dashboard role="student" :breadcrumb="['My Tests']" pageTitle="My Tests">
    <x-stub-screen
        title="My Tests"
        body="Assigned, in-progress, and completed tests live here. Returns to the dashboard for the demo."
        :backHref="route('student.dashboard')"
    />
</x-layouts.dashboard>
