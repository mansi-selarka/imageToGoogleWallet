// resources/js/Pages/GooglePass.jsx
import { Head } from '@inertiajs/react';

export default function GooglePass() {
    return (
        <>
            <Head title="Google Pass" />
            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="bg-gray-100 overflow-hidden shadow-sm rounded-lg">
                        <div className="p-10 text-gray-900">Download Your Pass</div>
                        <button
                            onClick={() => alert('Google Wallet added!')}
                            className="mt-4 ml-4 px-6 py-2 bg-green-500 text-white rounded"
                        >
                            Add to Google Wallet
                        </button>
                    </div>
                </div>
            </div>
        </>
    );
}
