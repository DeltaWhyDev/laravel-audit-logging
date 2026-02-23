<template>
    <div class="changelog-field w-full">
        <div v-if="logs.length === 0" class="text-90 text-center py-8">
            {{ __('No changes recorded yet.') }}
        </div>

        <div v-for="log in logs" :key="log.id" class="flex flex-col border-b border-gray-200 dark:border-gray-700 last:border-0">
            <!-- Header -->
            <div class="py-4 flex items-center justify-between"
                 :class="{'cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors duration-150 ease-in-out': !field.alwaysExpanded}"
                 @click="!field.alwaysExpanded && toggleLog(log)">
                <div class="flex items-center space-x-3">
                    <!-- Meta Info -->
                    <div>
                        <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100">
                            <!-- Actor Name -->
                            <span v-if="log.actor_url && log.actor_id">
                                <a :href="log.actor_url" class="font-bold text-primary-500 hover:text-primary-600 no-underline">
                                    {{ log.actor }}
                                </a>
                            </span>
                            <span v-else class="font-bold">
                                {{ log.actor || __('System') }}
                            </span>

                            <!-- Action -->
                            <span class="text-gray-500 dark:text-gray-400 mx-1">
                                {{ log.action_label.toLowerCase() }}
                            </span>

                            <!-- Entity (Target) -->
                            <span class="font-semibold text-gray-700 dark:text-gray-200">
                                <span v-if="log.entity_url && log.entity_id">
                                    <a :href="log.entity_url" class="font-bold text-primary-500 hover:text-primary-600 no-underline">
                                        {{ log.entity_name || resourceName }}
                                    </a>
                                </span>
                                <span v-else>
                                    {{ log.entity_name || resourceName }}
                                </span>
                            </span>

                            <!-- Modified Fields -->
                            <span v-if="log.summary && log.summary !== 'No changes detailed'" class="text-gray-500 dark:text-gray-400 ml-2 font-normal">
                                ({{ log.summary }})
                            </span>
                        </h4>
                    </div>
                </div>

                <!-- Timestamp & Toggle Icon -->
                <div class="flex items-center text-xs text-gray-400 dark:text-gray-500">
                    <span class="mr-3">{{ log.timestamp }}</span>
                    <svg v-if="!field.alwaysExpanded" class="h-5 w-5 transform transition-transform duration-200" :class="{'rotate-180': isExpanded(log.id)}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                    </svg>
                </div>
            </div>

            <!-- Content -->
            <div class="px-6 py-4 bg-gray-50/50 dark:bg-gray-900/30 border-t border-gray-100 dark:border-gray-700/50" v-if="field.alwaysExpanded || isExpanded(log.id)">
                <!-- Attribute Changes -->
                <div v-if="log.attributes && log.attributes.length > 0">
                    <dl class="divide-y divide-gray-100 dark:divide-gray-700">
                        <div v-for="(attr, index) in log.attributes" :key="'attr-'+index" class="py-3 grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <dt class="text-sm font-bold text-gray-500 dark:text-gray-400 self-center">
                                {{ attr.label }}
                            </dt>
                            <dd class="text-sm text-gray-900 dark:text-gray-100 sm:col-span-2 flex items-center flex-wrap gap-2">
                                <template v-if="attr.is_diff_row">
                                    <div class="flex items-center flex-wrap gap-2 w-full">
                                        <span v-if="!attr.old_is_empty" class="inline-flex items-center px-2.5 py-0.5 rounded-md text-sm font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 opacity-80" v-html="attr.old"></span>
                                        <div v-if="!attr.old_is_empty && !attr.new_is_empty" class="text-gray-400">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
                                            </svg>
                                        </div>

                                        <span v-if="!attr.new_is_empty" class="inline-flex items-center px-2.5 py-0.5 rounded-md text-sm font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200" v-html="attr.new"></span>
                                    </div>
                                </template>
                                <template v-else>
                                    <template v-if="!attr.old_is_empty">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-sm font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 opacity-80" v-html="attr.old"></span>
                                        <svg class="w-4 h-4 text-gray-300 dark:text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"></path>
                                        </svg>
                                    </template>

                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-md text-sm font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200" v-html="attr.new"></span>
                                </template>
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- Relations Changes -->
                <div v-if="log.relations && log.relations.length > 0" :class="{'mt-4 pt-4 border-t border-gray-100 dark:border-gray-700': log.attributes && log.attributes.length > 0}">
                    <h5 class="text-xs font-bold uppercase tracking-wider text-gray-400 mb-3">{{ __('Relations') }}</h5>
                    <dl class="divide-y divide-gray-100 dark:divide-gray-700">
                         <div v-for="(rel, index) in log.relations" :key="'rel-'+index" class="py-3 grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <dt class="text-sm font-bold text-gray-500 dark:text-gray-400 self-center">
                                {{ rel.label }}
                            </dt>
                            <dd class="text-sm text-gray-900 dark:text-gray-100 sm:col-span-2 space-y-1">
                                <!-- Added -->
                                <div v-if="rel.added && rel.added.length > 0" class="flex flex-wrap gap-1">
                                    <span v-for="(item, i) in rel.added" :key="'added-'+i" class="inline-flex items-center px-2.5 py-0.5 rounded-md text-sm font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                        <a v-if="item.url" :href="item.url" class="hover:underline">{{ item.name || item.id || item }}</a>
                                        <span v-else>{{ item.name || item.id || item }}</span>
                                    </span>
                                </div>
                                <!-- Removed -->
                                <div v-if="rel.removed && rel.removed.length > 0" class="flex flex-wrap gap-1">
                                    <span v-for="(item, i) in rel.removed" :key="'removed-'+i" class="inline-flex items-center px-2.5 py-0.5 rounded-md text-sm font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 opacity-80">
                                        <a v-if="item.url" :href="item.url" class="hover:underline">{{ item.name || item.id || item }}</a>
                                        <span v-else>{{ item.name || item.id || item }}</span>
                                    </span>
                                </div>
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- Empty State -->
                <div v-if="(!log.attributes || log.attributes.length === 0) && (!log.relations || log.relations.length === 0)" class="text-sm text-gray-500 italic py-2">
                    {{ __('No specific field changes recorded.') }}
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <div class="border-t border-gray-200 dark:border-gray-700 pt-4" v-if="logs.length > 0 && field.showPagination !== false">
            <nav class="flex justify-between items-center px-4 py-2 sm:px-6">
                <button
                    class="text-sm font-bold text-gray-300 dark:text-gray-600 cursor-not-allowed focus:outline-none"
                    disabled
                >
                    {{ __('Previous') }}
                </button>

                <button
                    class="text-sm font-bold text-gray-300 dark:text-gray-600 cursor-not-allowed focus:outline-none"
                    disabled
                >
                    {{ __('Next') }}
                </button>
            </nav>
        </div>
    </div>
</template>

<script>
export default {
    props: ['resource', 'resourceName', 'resourceId', 'field'],

    data() {
        return {
            logs: Array.isArray(this.field.value) ? this.field.value : (this.field.value ? [this.field.value] : []),
            expandedLogIds: [],
        };
    },

    methods: {
        toggleLog(log) {
            if (this.isExpanded(log.id)) {
                this.expandedLogIds = this.expandedLogIds.filter(id => id !== log.id);
            } else {
                this.expandedLogIds.push(log.id);
            }
        },
        isExpanded(id) {
            return this.expandedLogIds.includes(id);
        },
        getInitials(name) {
            if (!name) return '??';
            return name
                .split(' ')
                .map(n => n[0])
                .slice(0, 2)
                .join('')
                .toUpperCase();
        },
    },
};
</script>
