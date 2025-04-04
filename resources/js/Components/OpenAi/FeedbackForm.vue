<template>
    <div
        v-if="message.source === 'ai'"
        class="text-gray-400 dark:text-gray-600"
    >
        <div class="flex justify-end" v-if="message.feedback === null">
            <HandThumbUpIcon
                v-on:click="voteUp(message.id)"
                class="mx-2 h-6 w-6 hover:text-gray-900 dark:hover:text-gray-50"
            />
            <HandThumbDownIcon
                v-on:click="voteDown(message.id)"
                class="mx-2 h-6 w-6 hover:text-gray-900 dark:hover:text-gray-50"
            />
        </div>
        <div class="flex justify-end" v-else>
            <HandThumbUpIcon
                v-if="message.feedback"
                class="mx-2 h-6 w-6 text-gray-900 dark:text-gray-50"
            />
            <HandThumbDownIcon
                v-else
                class="dark:text-red-300` mx-2 h-6 w-6 text-red-600"
            />
        </div>
    </div>
</template>
<script setup>
import { HandThumbUpIcon } from '@heroicons/vue/24/solid/index.js';
import { HandThumbDownIcon } from '@heroicons/vue/24/solid/index.js';
import { useForm } from '@inertiajs/vue3';

defineProps(['message']);

const emit = defineEmits(['updateMessages']);

const voteForm = useForm({
    up: false,
    id: null,
});

const voteUp = (id) => {
    voteForm.up = true;
    voteForm.id = id;
    voteForm.post('/api/vote-message', {
        preserveScroll: true,
        onSuccess: (page) => {
            emit('updateMessages', page.props.messages);
        },
    });
};

const voteDown = (id) => {
    voteForm.up = false;
    voteForm.id = id;
    voteForm.post('/api/vote-message', {
        preserveScroll: true,
        onSuccess: (page) => {
            emit('updateMessages', page.props.messages);
        },
    });
};
</script>
