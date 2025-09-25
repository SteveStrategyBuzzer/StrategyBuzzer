
// IA QuizBot principal
export function generateQuestion(subject) {
    return {
        question: `Quelle est la capitale associée à ${subject} ?`,
        options: ["Paris", "Berlin", "Ottawa", "Tokyo"],
        answer: "Paris"
    };
}
