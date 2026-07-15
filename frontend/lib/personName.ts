export interface PersonNameLike {
  first_name?: string | null;
  last_name?: string | null;
  display_name?: string | null;
  name?: string | null;
}

export interface PersonNameFields {
  first_name: string;
  last_name: string;
}

const MAX_NAME_LENGTH = 255;
const CONTROL_CHARACTER = /\p{Cc}/u;

function normalizedNamePart(value: string | null | undefined): string {
  return value?.trim().replace(/\s+/gu, " ") ?? "";
}

function validateNamePart(value: string, label: string): string {
  if (CONTROL_CHARACTER.test(value)) {
    throw new Error(`${label} must not contain control characters.`);
  }

  const normalized = normalizedNamePart(value);
  if (!normalized) {
    throw new Error("First and last name are both required when a name is created or changed.");
  }
  if ([...normalized].length > MAX_NAME_LENGTH) {
    throw new Error(`${label} must not exceed ${MAX_NAME_LENGTH} characters.`);
  }

  return normalized;
}

export function personDisplayName(person: PersonNameLike | null | undefined, fallback = ""): string {
  if (!person) return fallback;
  if (person.display_name?.trim()) return person.display_name;

  const firstName = normalizedNamePart(person.first_name);
  const lastName = normalizedNamePart(person.last_name);
  if (firstName && lastName) return `${firstName} ${lastName}`;
  if (person.name?.trim()) return person.name;

  return firstName || lastName || fallback;
}

export function personNameFormValues(person: PersonNameLike): { firstName: string; lastName: string } {
  return {
    firstName: person.first_name ?? "",
    lastName: person.last_name ?? "",
  };
}

export function requiredPersonNameFields(firstName: string, lastName: string): PersonNameFields {
  return {
    first_name: validateNamePart(firstName, "First name"),
    last_name: validateNamePart(lastName, "Last name"),
  };
}

export function changedPersonNameFields(
  person: PersonNameLike,
  firstName: string,
  lastName: string,
): PersonNameFields | null {
  const currentFirstName = normalizedNamePart(person.first_name);
  const currentLastName = normalizedNamePart(person.last_name);
  const nextFirstName = normalizedNamePart(firstName);
  const nextLastName = normalizedNamePart(lastName);

  if (currentFirstName === nextFirstName && currentLastName === nextLastName) {
    return null;
  }

  return requiredPersonNameFields(firstName, lastName);
}
