<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2
                class="text-xl font-semibold leading-tight text-gray-800 dark:text-gray-200"
            >
                Dashboard
            </h2>
        </template>

        <div class="py-6">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div
                    class="overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800"
                >
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        You're logged in!
                    </div>
                </div>
            </div>
        </div>

        <div class="py-6">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div>
                    <div
                        v-for="message in messages"
                        :key="message.id"
                        :class="
                            message.source === 'ai' ? 'ml-6' : 'mt-6 shadow-md'
                        "
                        class="my-2 rounded-md bg-white px-4 py-4 text-gray-900 dark:bg-gray-800 dark:text-gray-100"
                    >
                        <div>
                            <span
                                :class="
                                    message.source === 'ai'
                                        ? 'text-red-500'
                                        : ''
                                "
                                class="font-bold"
                                >{{
                                    message.source === 'user'
                                        ? page.props.auth.user.name
                                        : 'The Amazing AI'
                                }}</span
                            >
                        </div>
                        <div>
                            <div v-if="message.source === 'user'">
                                {{ message.message }}
                            </div>
                            <div v-else>
                                <div v-html="md.render(message.message)" />
                            </div>
                            <div>
                                <h3
                                    v-if="message.annotations?.length"
                                    class="mt-2 font-bold"
                                >
                                    References
                                </h3>
                                <div
                                    v-for="(
                                        annotation, index
                                    ) in message.annotations"
                                    :key="index"
                                >
                                    <a
                                        class="text-blue-600 dark:text-blue-400"
                                        target="_blank"
                                        :href="annotation.url"
                                        >{{ annotation.note }}
                                        {{ annotation.title }}</a
                                    >
                                </div>
                                <FeedbackForm
                                    :message="message"
                                    @update-messages="updateMessages"
                                />
                            </div>
                        </div>
                    </div>
                </div>
                <div
                    class="mt-6 overflow-hidden bg-white shadow-sm sm:rounded-lg dark:bg-gray-800"
                >
                    <div
                        class="p-6 text-gray-900 dark:bg-gray-800 dark:text-gray-100"
                    >
                        <form @submit.prevent="submit">
                            <div class="mb-4">
                                <label
                                    class="mb-2 block text-sm font-bold text-gray-900 dark:bg-gray-800 dark:text-gray-100"
                                    for="question"
                                    >Ask your question*:</label
                                >
                                <textarea
                                    class="w-full rounded-md border px-3 py-2 text-gray-900 focus:border-blue-100 focus:outline-none focus:ring disabled:opacity-25 dark:bg-gray-200 dark:focus:ring-orange-600"
                                    id="question"
                                    :disabled="form.processing"
                                    required
                                    rows="5"
                                    v-model="form.prompt"
                                >
                                </textarea>
                                <div
                                    v-if="form.errors.prompt"
                                    class="text-sm text-red-600"
                                >
                                    {{ form.errors.prompt }}
                                </div>
                            </div>
                            <div
                                v-if="form.errors.general"
                                class="text-sm text-red-600"
                            >
                                {{ form.errors.general }}
                            </div>
                            <button
                                :disabled="form.processing"
                                class="focus:shadow-outline mx-1 rounded bg-blue-600 px-4 py-2 font-bold text-white hover:bg-gray-950 focus:outline-none disabled:opacity-25"
                                type="submit"
                            >
                                Send
                            </button>
                            <button
                                :disabled="form.processing"
                                v-on:click="resetThread"
                                class="focus:shadow-outline mx-1 rounded bg-blue-600 px-4 py-2 font-bold text-white hover:bg-gray-950 focus:outline-none disabled:opacity-25"
                            >
                                New Thread
                            </button>
                            <span class="px-5" v-if="form.processing"
                                >Thinking. Please wait...</span
                            >
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';
import markdownit from 'markdown-it';
import FeedbackForm from '@/Components/OpenAi/FeedbackForm.vue';

const md = markdownit();

const page = usePage();

const messages = ref(page.props.messages);
const resetThread = () => {
    form.newThread = true;
    form.prompt = 'reset-thread';
    form.submit();
};

const form = useForm({
    prompt: null,
    newThread: false,
});

const updateMessages = (newMessages) => {
    messages.value = newMessages;
};

onMounted(() => {
    console.log('Props on the page:', page.props);
});
const submit = () => {
    console.log('submit');
    form.post('/api/ask-ai', {
        preserveScroll: true,
        onSuccess: (page) => {
            messages.value = page.props.messages;
            form.reset();
        },
    });
};
</script>
