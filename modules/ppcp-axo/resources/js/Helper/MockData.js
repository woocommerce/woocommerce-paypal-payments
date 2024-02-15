
class MockData {

    static cardComponent() {
        return {
            fields: {
                phoneNumber: {
                    prefill: "1234567890"
                },
                cardholderName: {} // optionally pass this to show the card holder name
            }
        }
    }

    static cardComponentTokenize() {
        return {
            name: {
                fullName: "John Doe"
            },
            billingAddress: {
                addressLine1: "2211 North 1st St",
                adminArea1: "San Jose",
                adminArea2: "CA",
                postalCode: "95131",
                countryCode: "US"
            }
        }
    }

}

export default MockData;
