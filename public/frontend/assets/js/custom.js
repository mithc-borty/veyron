/*
============================================================
JAVASCRIPT DATA TYPES + JSDOC
============================================================

File:
public/frontend/assets/js/custom.js

Purpose:
Interactive JavaScript data type examples and JSDoc
static typing demonstrations.

Sections:

1.  String
2.  Number
3.  BigInt
4.  Boolean
5.  Undefined
6.  Null
7.  Symbol
8.  Object
9.  Array
10. Array of Objects
11. Function
12. typeof
13. JSDoc Static Typing Behaviour

IMPORTANT:

JSDoc provides type information to development tools.

It does NOT change JavaScript runtime behaviour.
*/


/*
============================================================
1. STRING
============================================================
*/

document
    .getElementById("stringButton")
    .addEventListener("click", function () {

        /** @type {string} */
        const value =
            document.getElementById("stringInput").value;

        document.getElementById("stringResult").textContent =
            `Value: ${value}

Type: ${typeof value}

Length: ${value.length}`;
    });


/*
============================================================
2. NUMBER
============================================================
*/

document
    .getElementById("numberButton")
    .addEventListener("click", function () {

        /** @type {number} */
        const value =
            Number(
                document.getElementById("numberInput").value
            );

        document.getElementById("numberResult").textContent =
            `Value: ${value}

Type: ${typeof value}

Is integer: ${Number.isInteger(value)}`;
    });


/*
============================================================
3. BIGINT
============================================================
*/

document
    .getElementById("bigIntButton")
    .addEventListener("click", function () {

        try {

            /** @type {bigint} */
            const value =
                BigInt(
                    document
                        .getElementById("bigIntInput")
                        .value
                );

            document.getElementById("bigIntResult").textContent =
                `Value: ${value}

Type: ${typeof value}`;

        } catch (error) {

            document.getElementById("bigIntResult").textContent =
                "Invalid BigInt value.";
        }
    });


/*
============================================================
4. BOOLEAN
============================================================
*/

document
    .getElementById("booleanButton")
    .addEventListener("click", function () {

        /** @type {boolean} */
        const value =
            document.getElementById("booleanInput").value === "true";

        document.getElementById("booleanResult").textContent =
            `Value: ${value}

Type: ${typeof value}`;
    });


/*
============================================================
5. UNDEFINED
============================================================
*/

document
    .getElementById("undefinedButton")
    .addEventListener("click", function () {

        /** @type {undefined} */
        let value;

        document.getElementById("undefinedResult").textContent =
            `Value: ${value}

Type: ${typeof value}`;
    });


/*
============================================================
6. NULL
============================================================
*/

document
    .getElementById("nullButton")
    .addEventListener("click", function () {

        /** @type {null} */
        const value = null;

        document.getElementById("nullResult").textContent =
            `Value: ${value}

typeof value: ${typeof value}

Important:
typeof null === "object"

This is a historical JavaScript behaviour.`;
    });


/*
============================================================
7. SYMBOL
============================================================
*/

document
    .getElementById("symbolButton")
    .addEventListener("click", function () {

        /** @type {symbol} */
        const value =
            Symbol(
                document
                    .getElementById("symbolInput")
                    .value
            );

        document.getElementById("symbolResult").textContent =
            `Description: ${value.description}

Type: ${typeof value}`;
    });


/*
============================================================
8. OBJECT
============================================================
*/

document
    .getElementById("objectButton")
    .addEventListener("click", function () {

        /**
         * @type {{
         *     name: string,
         *     age: number,
         *     active: boolean
         * }}
         */
        const user = {

            name:
                document
                    .getElementById("nameInput")
                    .value,

            age:
                Number(
                    document
                        .getElementById("ageInput")
                        .value
                ),

            active:
                document
                    .getElementById("activeInput")
                    .value === "true"
        };

        document.getElementById("objectResult").textContent =
            `Object:

${JSON.stringify(user, null, 2)}

Type:
${typeof user}

Name:
${user.name}

Age:
${user.age}

Active:
${user.active}`;
    });


/*
============================================================
9. ARRAY
============================================================
*/

document
    .getElementById("arrayButton")
    .addEventListener("click", function () {

        /** @type {string[]} */
        const values =
            document
                .getElementById("arrayInput")
                .value
                .split(",")
                .map(function (value) {

                    return value.trim();
                });

        document.getElementById("arrayResult").textContent =
            `Array:

${JSON.stringify(values, null, 2)}

Is Array:
${Array.isArray(values)}

Length:
${values.length}

First item:
${values[0]}`;
    });


/*
============================================================
10. ARRAY OF OBJECTS
============================================================
*/

document
    .getElementById("usersButton")
    .addEventListener("click", function () {

        /**
         * @type {{
         *     id: number,
         *     name: string,
         *     active: boolean
         * }[]}
         */
        const users = [

            {
                id: 1,
                name: "Mithun",
                active: true
            },

            {
                id: 2,
                name: "Rahul",
                active: false
            },

            {
                id: 3,
                name: "Anita",
                active: true
            }
        ];

        document.getElementById("usersResult").textContent =
            `Users:

${JSON.stringify(users, null, 2)}

Number of users:
${users.length}

First user's name:
${users[0].name}

Second user's status:
${users[1].active}`;
    });


/*
============================================================
11. FUNCTION
============================================================
*/

/**
 * Add two numbers.
 *
 * @param {number} a First number.
 * @param {number} b Second number.
 * @returns {number} Sum of the numbers.
 */
function add(a, b) {

    return a + b;
}


document
    .getElementById("functionButton")
    .addEventListener("click", function () {

        /** @type {number} */
        const a =
            Number(
                document
                    .getElementById("firstNumber")
                    .value
            );

        /** @type {number} */
        const b =
            Number(
                document
                    .getElementById("secondNumber")
                    .value
            );

        const result = add(a, b);

        document.getElementById("functionResult").textContent =
            `a = ${a}

b = ${b}

Result = ${result}

Result type = ${typeof result}`;
    });


/*
============================================================
12. TYPEOF
============================================================
*/

document
    .getElementById("typeofButton")
    .addEventListener("click", function () {

        /** @type {string} */
        const stringValue = "Hello";

        /** @type {number} */
        const numberValue = 100;

        /** @type {bigint} */
        const bigIntValue = 100n;

        /** @type {boolean} */
        const booleanValue = true;

        /** @type {undefined} */
        let undefinedValue;

        /** @type {null} */
        const nullValue = null;

        /** @type {symbol} */
        const symbolValue = Symbol("id");

        /** @type {{name: string}} */
        const objectValue = {
            name: "Mithun"
        };

        /** @type {string[]} */
        const arrayValue = [
            "JavaScript",
            "PHP"
        ];

        /*
        Functions are objects internally,
        but typeof returns "function".
        */
        function functionValue() { }

        const output = [

            `String:     ${typeof stringValue}`,

            `Number:     ${typeof numberValue}`,

            `BigInt:     ${typeof bigIntValue}`,

            `Boolean:    ${typeof booleanValue}`,

            `Undefined:  ${typeof undefinedValue}`,

            `Null:       ${typeof nullValue}`,

            `Symbol:     ${typeof symbolValue}`,

            `Object:     ${typeof objectValue}`,

            `Array:      ${typeof arrayValue}`,

            `Function:   ${typeof functionValue}`
        ];

        document.getElementById("typeofResult").textContent =
            output.join("\n");
    });


/*
============================================================
13. JSDOC STATIC TYPING BEHAVIOUR
============================================================

JSDoc describes expected types for development/static tools.

It does NOT perform runtime type conversion.

The following examples demonstrate the difference between:

    STATIC TYPE INFORMATION

and

    RUNTIME JAVASCRIPT BEHAVIOUR
*/


/*
------------------------------------------------------------
13.1 VARIABLE TYPE ANNOTATION
------------------------------------------------------------
*/

document
    .getElementById("jsdocVariableButton")
    .addEventListener("click", function () {

        /**
         * JSDoc says this variable should contain a number.
         *
         * @type {number}
         */
        const value = "100";

        document.getElementById("jsdocVariableResult").textContent =
            `JSDoc declaration:

@type {number}

Actual value:
${value}

Actual runtime type:
${typeof value}

Result:

JSDoc did NOT convert the string
into a number.

The runtime value is still a string.`;
    });


/*
------------------------------------------------------------
13.2 JSDOC VS RUNTIME CONVERSION
------------------------------------------------------------
*/

document
    .getElementById("jsdocConversionButton")
    .addEventListener("click", function () {

        const value = "100";

        /**
         * JSDoc annotation.
         *
         * This does not convert the value.
         *
         * @type {number}
         */
        const jsdocValue = value;

        /*
        Actual runtime conversion.
        */
        const convertedValue = Number(value);

        document.getElementById("jsdocConversionResult").textContent =
            `Original value:
${value}

Original runtime type:
${typeof value}


JSDoc annotated value:
${jsdocValue}

JSDoc annotated runtime type:
${typeof jsdocValue}


Number() converted value:
${convertedValue}

Number() converted runtime type:
${typeof convertedValue}


Conclusion:

JSDoc
→ static/development information

Number()
→ actual runtime conversion`;
    });


/*
------------------------------------------------------------
13.3 OBJECT TYPE
------------------------------------------------------------
*/

document
    .getElementById("jsdocObjectButton")
    .addEventListener("click", function () {

        /**
         * Describe the expected object structure.
         *
         * @type {{
         *     name: string,
         *     age: number
         * }}
         */
        const user = {

            name: "Mithun",

            age: 40
        };

        document.getElementById("jsdocObjectResult").textContent =
            `User object:

${JSON.stringify(user, null, 2)}


user.name:
${user.name}

Runtime type:
${typeof user.name}


user.age:
${user.age}

Runtime type:
${typeof user.age}


Runtime type of user:
${typeof user}


JSDoc describes the expected
structure of the object.`;
    });


/*
------------------------------------------------------------
13.4 ARRAY TYPE
------------------------------------------------------------
*/

document
    .getElementById("jsdocArrayButton")
    .addEventListener("click", function () {

        /**
         * An array whose elements should be strings.
         *
         * @type {string[]}
         */
        const languages = [

            "JavaScript",

            "PHP",

            "Python"
        ];

        document.getElementById("jsdocArrayResult").textContent =
            `Array:

${JSON.stringify(languages, null, 2)}


Array.isArray():
${Array.isArray(languages)}


Runtime type:
${typeof languages}


First item:
${languages[0]}


First item runtime type:
${typeof languages[0]}


JSDoc type:

string[]

means:

Array of strings`;
    });


/*
------------------------------------------------------------
13.5 FUNCTION TYPE
------------------------------------------------------------
*/

/**
 * Add two numbers.
 *
 * @param {number} a First number.
 * @param {number} b Second number.
 * @returns {number} Sum of the numbers.
 */
function addNumbers(a, b) {

    return a + b;
}


document
    .getElementById("jsdocFunctionButton")
    .addEventListener("click", function () {

        const a = 10;

        const b = 20;

        const result = addNumbers(a, b);

        document.getElementById("jsdocFunctionResult").textContent =
            `a:
${a}


b:
${b}


Result:
${result}


Result runtime type:
${typeof result}


JSDoc declares:

@param {number} a

@param {number} b

@returns {number}`;
    });


/*
------------------------------------------------------------
13.6 TYPE ASSERTION
------------------------------------------------------------
*/

document
    .getElementById("jsdocAssertionButton")
    .addEventListener("click", function () {

        const value = "100";

        /*
        JSDoc type assertion.

        This tells the static checker
        how the expression should be treated.

        It does NOT convert the runtime value.
        */

        const assertedValue =
            /** @type {number} */
            (value);

        document.getElementById("jsdocAssertionResult").textContent =
            `Original value:
${value}


Original runtime type:
${typeof value}


JSDoc asserted value:
${assertedValue}


Runtime type after assertion:
${typeof assertedValue}


Important:

The assertion changes the
static type information available
to development tools.

It does NOT change the
JavaScript runtime value.`;
    });


/*
------------------------------------------------------------
13.7 DOM TYPE ASSERTION
------------------------------------------------------------
*/

document
    .getElementById("jsdocDomButton")
    .addEventListener("click", function () {

        /*
        Tell the static checker that this
        DOM element should be treated as
        an HTMLInputElement.
        */

        const input =
            /** @type {HTMLInputElement} */
            (
                document.getElementById("stringInput")
            );

        input.focus();

        document.getElementById("jsdocDomResult").textContent =
            `Element ID:
${input.id}


Element value:
${input.value}


Runtime constructor:
${input.constructor.name}


Runtime typeof:
${typeof input}


JSDoc type:

HTMLInputElement


JSDoc gives development tools
more specific information about
the DOM element.

The assertion does not create
a new element.`;
    });